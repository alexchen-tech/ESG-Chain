<?php

namespace App\Services\SAQ;

use App\Jobs\DispatchSaqScoringJob;
use App\Models\SAQ;
use App\Models\SaqProject;
use App\Models\Supplier;
use App\Services\Disclosure\DisclosureSyncService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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

        // 觸發 FastAPI 計分（非同步，走 Celery：與 DispatchSaqScoringJob 統一路徑）
        DispatchSaqScoringJob::dispatch($saq->id);

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

    /**
     * 儲存六維度分數，並以 series.dim_weights 合成總分/分級後寫回 SAQ。
     */
    public function applyDimScores(SAQ $saq, array $dims): SAQ
    {
        $synthScore = $this->synthesizeDimScore($saq, $dims);
        $synthGrade = $this->deriveGrade($synthScore, $saq->project?->series?->grade_thresholds ?? []);

        $saq->update(array_merge($dims, [
            'score' => $synthScore,
            'grade' => $synthGrade,
        ]));

        return $saq->fresh();
    }

    /**
     * 以六維度分數與 series.dim_weights 合成 0–100 總分。
     * dim_eN 已為 0–100 scale（AI 端輸出），直接加權。
     */
    public function synthesizeDimScore(SAQ $saq, array $dims): float
    {
        $series = $saq->project?->series;
        $weights = $series?->dim_weights
            ?? app(SystemSettingsService::class)->getDimWeightDefaults();

        $keys = ['E1'=>'dim_e1','E2'=>'dim_e2','E3'=>'dim_e3','E4'=>'dim_e4','E5'=>'dim_e5','E6'=>'dim_e6'];
        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($keys as $eKey => $dimKey) {
            $val = $dims[$dimKey] ?? null;
            $w   = $weights[$eKey] ?? 0;
            if ($val !== null && $w > 0) {
                $weightedSum += $val * $w;
                $weightTotal += $w;
            }
        }

        return $weightTotal > 0 ? round($weightedSum / $weightTotal, 2) : 0.0;
    }

    public function deriveGrade(float $score, array $thresholds): string
    {
        if ($score >= ($thresholds['A'] ?? 80.0)) return 'A';
        if ($score >= ($thresholds['B'] ?? 60.0)) return 'B';
        if ($score >= ($thresholds['C'] ?? 40.0)) return 'C';
        if ($score >= ($thresholds['D'] ?? 20.0)) return 'D';
        return 'E';
    }

    /**
     * LLM 文字題評分回寫：重算 raw_score = llm_score/100 * weight。
     * 使用參數綁定，避免字串插值組 Raw SQL。
     */
    public function applyLlmScore(SAQ $saq, string $projectQuestionId, float $llmScore, ?string $reason): void
    {
        $weight = DB::table('project_questions')
            ->where('id', $projectQuestionId)
            ->value('weight');

        $rawScore = $llmScore / 100.0 * (float) ($weight ?? 0);

        $saq->responses()
            ->where('project_question_id', $projectQuestionId)
            ->update([
                'llm_score'        => $llmScore,
                'llm_score_reason' => $reason,
                'score_confidence' => 'medium',
                'raw_score'        => $rawScore,
            ]);
    }
}
