<?php

namespace App\Services\Questionnaire;

use App\Jobs\DispatchSaqScoringJob;
use App\Models\SAQ;
use App\Models\SasbIndustry;
use App\Models\Supplier;
use App\Models\SAQTemplate;
use App\Services\SAQ\SaqScoreSnapshotService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class QuestionnaireService
{
    // 合法狀態轉換表
    private const TRANSITIONS = [
        'sent'            => ['start_fill' => 'in_progress', 'submit' => 'submitted'],
        'in_progress'     => ['submit' => 'submitted'],
        'submitted'       => ['start_review' => 'under_review'],
        'under_review'    => ['complete_review' => 'completed', 'return_review' => 'review_returned'],
        'review_returned' => ['submit' => 'submitted'],
        'completed'       => ['mark_reviewed' => 'reviewed'],
        'reviewed'        => [],
        // 申訴流程（saq-scoring-v2）
        'disputed'        => ['re_review' => 're_review'],
        're_review'       => ['finalize' => 'finalized'],
        'finalized'       => [],
    ];

    public function list(array $filters = []): LengthAwarePaginator
    {
        // 供應商角色一律強制以自己的 supplier_id 過濾，不管請求是否帶入 supplier_id，
        // 避免不帶該參數即可繞過而看到全站問卷（IDOR 修復）
        $user = auth()->user();
        $roles = $user?->getRoleNames()->toArray() ?? [];
        if (in_array('supplier', $roles, true) || in_array('sup_esg', $roles, true)) {
            $filters['supplier_id'] = $user->supplier_id;
        }

        return SAQ::with(['supplier', 'template', 'project'])
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['supplier_id'] ?? null, fn($q, $v) => $q->where('supplier_id', $v))
            ->when($filters['template_id'] ?? null, fn($q, $v) => $q->where('template_id', $v))
            ->when($filters['project_id'] ?? null, fn($q, $v) => $q->where('project_id', $v))
            ->when(!empty($filters['overdue']), fn($q) => $q
                ->whereIn('status', ['sent', 'in_progress'])
                ->whereHas('project', fn($pq) => $pq->whereNotNull('due_date')->where('due_date', '<', now()->toDateString()))
            )
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 20, ['*'], 'page', $filters['page'] ?? 1);
    }

    public function counts(): array
    {
        $justSubmitted  = SAQ::where('status', 'submitted')->count();
        $underReview    = SAQ::where('status', 'under_review')->count();
        $overdueCount   = SAQ::whereIn('status', ['sent', 'in_progress'])
            ->whereHas('project', fn($q) => $q->whereNotNull('due_date')->where('due_date', '<', now()->toDateString()))
            ->count();
        $cumulativeStatuses = ['submitted', 'under_review', 'review_returned', 'completed', 'reviewed'];
        $submittedTotal = SAQ::whereIn('status', $cumulativeStatuses)->count();

        return [
            'just_submitted_count' => $justSubmitted,
            'under_review_count'   => $underReview,
            'overdue_count'        => $overdueCount,
            'submitted_count'      => $submittedTotal,
        ];
    }

    public function send(array $data, string $sentBy): array
    {
        $created = [];
        foreach ($data['supplier_ids'] as $supplierId) {
            $created[] = SAQ::create([
                'supplier_id' => $supplierId,
                'template_id' => $data['template_id'],
                'project_id'  => $data['project_id'] ?? null,
                'deadline'    => $data['deadline'] ?? null,
                'status'      => 'sent',
                'sent_at'     => Carbon::now(),
            ]);
        }
        return $created;
    }

    public function update(SAQ $saq, array $answers): SAQ
    {
        // 儲存回覆：key 為 project_question_id
        foreach ($answers as $projectQuestionId => $answer) {
            $saq->responses()->updateOrCreate(
                ['project_question_id' => $projectQuestionId],
                [
                    'answer'         => is_array($answer) ? null : $answer,
                    'answer_options' => is_array($answer) ? $answer : null,
                ]
            );
        }

        if ($saq->status === 'sent') {
            $saq->update(['status' => 'in_progress']);
        }

        return $saq->fresh();
    }

    public function submit(SAQ $saq, string $userId): SAQ
    {
        $this->assertCanTransition($saq, 'submit');
        $this->assertRequiredAnswered($saq);
        $saq->update([
            'status' => 'submitted',
            'submitted_at' => Carbon::now(),
        ]);
        $saq->reviewHistories()->create(['action' => 'submit', 'acted_by' => $userId]);
        DispatchSaqScoringJob::dispatch($saq->id);
        return $saq->fresh();
    }

    public function startReview(SAQ $saq, string $userId): SAQ
    {
        $this->assertCanTransition($saq, 'start_review');
        $saq->update([
            'status' => 'under_review',
            'review_started_at' => Carbon::now(),
            'reviewed_by_id' => $userId,
        ]);
        $saq->reviewHistories()->create(['action' => 'start_review', 'acted_by' => $userId]);
        return $saq->fresh();
    }

    public function completeReview(SAQ $saq, string $userId, ?string $comment): SAQ
    {
        $this->assertCanTransition($saq, 'complete_review');
        $saq->update(['status' => 'completed', 'reviewed_at' => Carbon::now()]);
        $saq->reviewHistories()->create(['action' => 'complete_review', 'acted_by' => $userId, 'comment' => $comment]);
        return $saq->fresh();
    }

    public function returnReview(SAQ $saq, string $userId, string $comment): SAQ
    {
        $this->assertCanTransition($saq, 'return_review');
        $saq->update(['status' => 'review_returned']);
        $saq->reviewHistories()->create(['action' => 'return_review', 'acted_by' => $userId, 'comment' => $comment]);
        return $saq->fresh();
    }

    public function markReviewed(SAQ $saq, string $userId): SAQ
    {
        $this->assertCanTransition($saq, 'mark_reviewed');
        $saq->update(['status' => 'reviewed']);
        $saq->reviewHistories()->create(['action' => 'mark_reviewed', 'acted_by' => $userId]);
        return $saq->fresh();
    }

    /**
     * 供應商申訴（7 天窗口期內，completed 狀態）
     */
    public function dispute(SAQ $saq, string $userId, string $reason): SAQ
    {
        if ($saq->status !== 'completed') {
            abort(422, json_encode([
                'success' => false, 'error_code' => 'BUSINESS_ERROR',
                'message' => "問卷狀態「{$saq->status}」不允許申訴，僅 completed 狀態可申訴",
            ]));
        }
        if ($saq->reviewed_at && $saq->reviewed_at->diffInDays(Carbon::now()) > 7) {
            abort(422, json_encode([
                'success' => false, 'error_code' => 'BUSINESS_ERROR',
                'message' => '申訴期限已過（審核完成後 7 天內可申訴）',
            ]));
        }
        $saq->update(['status' => 'disputed', 'disputed_at' => Carbon::now()]);
        $saq->reviewHistories()->create(['action' => 'dispute', 'acted_by' => $userId, 'comment' => $reason]);
        return $saq->fresh();
    }

    /**
     * 審核員接受申訴，開始重新審核
     */
    public function startReReview(SAQ $saq, string $userId): SAQ
    {
        $this->assertCanTransition($saq, 're_review');
        $saq->update(['status' => 're_review']);
        $saq->reviewHistories()->create(['action' => 're_review', 'acted_by' => $userId]);
        return $saq->fresh();
    }

    /**
     * 審核員最終確認，鎖定 final_score（終態）
     */
    public function finalize(SAQ $saq, string $userId, ?string $comment, SaqScoreSnapshotService $snapshotService): SAQ
    {
        $this->assertCanTransition($saq, 'finalize');
        $saq->update(['status' => 'finalized']);
        $saq->reviewHistories()->create(['action' => 'finalize', 'acted_by' => $userId, 'comment' => $comment]);

        // 建立 re_review 快照，記錄最終確認的分數
        $snapshotService->create($saq, 're_review', $userId, $saq->final_score, $saq->final_grade);

        return $saq->fresh();
    }

    public function recommendTemplates(array $supplierIds): array
    {
        $result = [];
        $templates = SAQTemplate::with('industries')->where('is_active', true)->get();

        foreach ($supplierIds as $supplierId) {
            $supplier = Supplier::with('sasbIndustry')->find($supplierId);
            if (!$supplier) continue;

            $supplierIndustryId   = $supplier->sasb_industry_id;
            $supplierIndustry     = $supplier->sasbIndustry;
            $supplierSector       = $supplierIndustry?->sector;

            $scored = $templates->map(function (SAQTemplate $t) use ($supplierIndustryId, $supplierSector) {
                $industryIds = $t->industries->pluck('id')->toArray();
                $hasSasb     = count($industryIds) > 0 || $t->sasb_industry_id;

                if (!$hasSasb) {
                    $matchType = 'generic';
                    $score     = 1;
                } elseif ($supplierIndustryId && (in_array($supplierIndustryId, $industryIds) || $t->sasb_industry_id === $supplierIndustryId)) {
                    $matchType = 'exact';
                    $score     = 3;
                } elseif ($supplierSector) {
                    $sectorMatch = $t->industries->contains(fn($i) => $i->sector === $supplierSector)
                        || ($t->sasbIndustry && $t->sasbIndustry->sector === $supplierSector);
                    $matchType = $sectorMatch ? 'sector' : 'incompatible';
                    $score     = $sectorMatch ? 2 : 0;
                } else {
                    $matchType = 'generic';
                    $score     = 1;
                }

                return ['template' => $t, 'match_type' => $matchType, 'score' => $score];
            })
            ->filter(fn($item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->values();

            $result[$supplierId] = $scored->map(fn($item) => [
                'id'         => $item['template']->id,
                'name'       => $item['template']->name,
                'version'    => $item['template']->version,
                'match_type' => $item['match_type'],
            ])->toArray();
        }

        return $result;
    }

    private function assertRequiredAnswered(SAQ $saq): void
    {
        if (!$saq->project_id) return;

        $requiredIds = \App\Models\ProjectQuestion::where('project_id', $saq->project_id)
            ->where('is_required', true)
            ->pluck('id');

        if ($requiredIds->isEmpty()) return;

        $answeredIds = $saq->responses()
            ->whereIn('project_question_id', $requiredIds)
            ->where(function ($q) {
                $q->where(function ($q1) {
                    $q1->whereNotNull('answer')->where('answer', '!=', '');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('answer_options')
                       ->whereRaw("JSON_LENGTH(answer_options) > 0");
                });
            })
            ->pluck('project_question_id');

        $unanswered = $requiredIds->diff($answeredIds);
        if ($unanswered->isNotEmpty()) {
            $count = $unanswered->count();
            abort(422, json_encode([
                'success' => false,
                'error_code' => 'REQUIRED_UNANSWERED',
                'message' => "有 {$count} 道必填題目尚未作答，請填寫後再提交",
                'unanswered_question_ids' => $unanswered->values(),
            ]));
        }
    }

    private function assertCanTransition(SAQ $saq, string $action): void
    {
        $allowed = self::TRANSITIONS[$saq->status] ?? [];
        if (!isset($allowed[$action])) {
            abort(400, json_encode([
                'success' => false,
                'error_code' => 'BUSINESS_ERROR',
                'message' => "問卷狀態「{$saq->status}」不允許執行「{$action}」操作",
            ]));
        }
    }
}
