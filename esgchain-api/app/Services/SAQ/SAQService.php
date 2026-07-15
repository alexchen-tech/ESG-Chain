<?php

namespace App\Services\SAQ;

use App\Models\SAQ;
use App\Models\SaqProject;
use App\Models\Supplier;
use App\Services\Disclosure\DisclosureSyncService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

class SAQService
{
    public function listByProject(SaqProject $project): array
    {
        return $project->saqs()->with(['supplier' => fn($q) => $q->withTrashed()])->get()->toArray();
    }

    public function listForSupplier(string $supplierId): LengthAwarePaginator
    {
        return SAQ::where('supplier_id', $supplierId)
            ->with(['project', 'template'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }

    /**
     * 依供應商的 industry_group 與適用法規，決定此次問卷的作用維度清單與法規快照。
     * 回傳：['active_modules' => [...], 'regulations' => [...]]
     */
    public function buildQuestionSet(Supplier $supplier): array
    {
        $moduleMap   = config('industry_module_map', []);
        $core        = ['E1', 'E4'];
        $addons      = $moduleMap[$supplier->industry_group] ?? [];
        $allModules  = array_values(array_unique(array_merge($core, $addons)));

        // 收集供應商所有 SalesProduct 的 applicable_regulations + inferred_regulations
        $products = DB::table('sales_products')
            ->where('supplier_id', $supplier->id)
            ->whereNull('deleted_at')
            ->get(['applicable_regulations', 'inferred_regulations']);

        $regulations = [];
        foreach ($products as $p) {
            $app      = json_decode($p->applicable_regulations ?? '[]', true) ?: [];
            $inferred = json_decode($p->inferred_regulations ?? '[]', true) ?: [];
            $regulations = array_merge($regulations, $app, $inferred);
        }
        $regulations = array_values(array_unique($regulations));

        // 若有適用法規且模組含 E6，確保 E6 已在清單中
        if (!empty($regulations) && !in_array('E6', $allModules)) {
            $allModules[] = 'E6';
        }

        return [
            'active_modules' => $allModules,
            'regulations'    => $regulations,
        ];
    }

    /**
     * 發送問卷給供應商
     */
    public function send(SaqProject $project, Supplier $supplier, string $sentBy): SAQ
    {
        $questionSet = $this->buildQuestionSet($supplier);

        $saq = SAQ::create([
            'project_id'           => $project->id,
            'supplier_id'          => $supplier->id,
            'template_id'          => $project->template_id,
            'status'               => 'sent',
            'sent_at'              => Carbon::now(),
            'active_modules'       => $questionSet['active_modules'],
            'regulations_snapshot' => $questionSet['regulations'],
        ]);

        $saq->reviewHistories()->create([
            'action' => 'send',
            'acted_by' => $sentBy,
            'comment' => '問卷已發送',
        ]);

        return $saq->load('supplier');
    }

    /**
     * 供應商提交問卷 → 觸發 FastAPI 計分
     */
    public function submit(SAQ $saq, array $responses, string $submittedBy): SAQ
    {
        // 儲存回覆
        foreach ($responses as $response) {
            $saq->responses()->updateOrCreate(
                ['question_id' => $response['question_id']],
                [
                    'answer' => $response['answer'] ?? null,
                    'answer_options' => $response['answer_options'] ?? null,
                    'evidence_note' => $response['evidence_note'] ?? null,
                ]
            );
        }

        $saq->update([
            'status' => 'submitted',
            'submitted_at' => Carbon::now(),
        ]);

        $saq->reviewHistories()->create([
            'action' => 'submit',
            'acted_by' => $submittedBy,
            'comment' => '問卷已提交',
        ]);

        // 觸發 FastAPI 計分（非同步）
        $this->triggerScoring($saq);

        return $saq->fresh();
    }

    /**
     * 審查員核准
     */
    public function approve(SAQ $saq, string $reviewedBy, ?string $comment): SAQ
    {
        $saq->update([
            'status' => 'approved',
            'reviewed_at' => Carbon::now(),
        ]);

        $saq->reviewHistories()->create([
            'action' => 'approve',
            'acted_by' => $reviewedBy,
            'comment' => $comment,
        ]);

        return $saq->fresh();
    }

    /**
     * 審查員退回
     */
    public function reject(SAQ $saq, string $reviewedBy, ?string $comment): SAQ
    {
        $saq->update([
            'status' => 'rejected',
            'reviewed_at' => Carbon::now(),
        ]);

        $saq->reviewHistories()->create([
            'action' => 'reject',
            'acted_by' => $reviewedBy,
            'comment' => $comment,
        ]);

        return $saq->fresh();
    }

    /**
     * FastAPI callback：計分結果回填
     */
    public function updateScore(
        SAQ $saq,
        float $score,
        string $grade,
        string $jobId,
        ?float $scoreE = null,
        ?float $scoreS = null,
        ?float $scoreG = null,
        ?string $scoringModelId = null,
        ?array $categoryScores = null,
    ): SAQ {
        // 計分完成後：若仍在 submitted 則自動推進至 under_review（審核準備完成）
        // 已在 under_review 之後的狀態則不變，避免倒退
        $nextStatus = $saq->status === 'submitted' ? 'under_review' : $saq->status;

        $saq->update([
            'score'            => $score,
            'grade'            => $grade,
            'scoring_job_id'   => $jobId,
            'score_e'          => $scoreE,
            'score_s'          => $scoreS,
            'score_g'          => $scoreG,
            'category_scores'  => $categoryScores,
            'scoring_model_id' => $scoringModelId,
            'status'           => $nextStatus,
        ]);

        app(DisclosureSyncService::class)->syncFromSaq($saq);

        $saq->reviewHistories()->create([
            'action'   => 'scoring_complete',
            'acted_by' => null,
            'comment'  => sprintf('計分完成：%.2f 分（%s 級），狀態推進至「%s」', $score, $grade, [
                'submitted'      => '已提交',
                'under_review'   => '審核中',
                'review_returned'=> '退回修改',
                'completed'      => '已完成',
                're_review'      => '重新審核',
                'finalized'      => '已定案',
                'reviewed'       => '已複核',
            ][$nextStatus] ?? $nextStatus),
        ]);

        return $saq->fresh();
    }

    private function triggerScoring(SAQ $saq): void
    {
        $aiUrl = config('services.ai.url', env('AI_SERVICE_URL', 'http://esgchain-ai:8000'));

        try {
            $saq->loadMissing([
                'supplier.sasbIndustry',
                'responses.question.sasbTopic',
                'responses.question.questionTags',
                'project.template',
            ]);

            // scoring_framework 由範本決定（project domain 為向後相容 fallback）
            $scoringFramework = $saq->project?->template?->scoring_framework;

            $responses = $saq->responses->map(fn($r) => [
                'question_id'      => $r->question_id,
                'source_question_id' => $r->question->source_bank_question_id,
                'weight'           => $r->question->weight ?? 1.0,
                'answer'           => $r->answer,
                'answer_options'   => $r->answer_options,
                'sasb_topic'       => $r->question->sasbTopic?->topic_name,
                'tag_slugs'        => $r->question->questionTags->pluck('slug')->toArray(),
            ])->toArray();

            $industryCode = $saq->supplier?->sasbIndustry?->code;

            $response = Http::timeout(10)->post("{$aiUrl}/ai/v1/scoring/saq", [
                'saq_id'              => $saq->id,
                'scoring_framework'   => $scoringFramework,
                'responses'           => $responses,
                'sasb_industry_code'  => $industryCode,
                'callback_url'        => rtrim(env('LARAVEL_INTERNAL_URL', 'http://esgchain-api:8080'), '/') . "/api/v1/internal/saqs/{$saq->id}/score-callback",
            ]);

            if ($response->successful()) {
                $jobId = $response->json('job_id');
                $saq->update(['scoring_job_id' => $jobId]);
            }
        } catch (\Throwable) {
            // 計分失敗不中斷主流程，待重試
        }
    }
}
