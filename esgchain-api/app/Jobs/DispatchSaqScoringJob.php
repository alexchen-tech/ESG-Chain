<?php

namespace App\Jobs;

use App\Models\CountryRiskRating;
use App\Models\ProjectQuestion;
use App\Models\SAQ;
use App\Models\QuestionTag;
use App\Models\SAQQuestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchSaqScoringJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly string $saqId) {}

    public function handle(): void
    {
        $saq = SAQ::with(['responses', 'project.template', 'project.series', 'supplier.sasbIndustry'])->find($this->saqId);
        if (!$saq) {
            Log::warning("DispatchSaqScoringJob: SAQ 不存在", ['saq_id' => $this->saqId]);
            return;
        }

        // 取 project 所有 ProjectQuestion（確保未作答題目也進入計分，計 0 分）
        $allPqs = $saq->project_id
            ? ProjectQuestion::where('project_id', $saq->project_id)->get()->keyBy('id')
            : collect();

        // 若無 project，fallback 至已有回覆的題目
        if ($allPqs->isEmpty()) {
            $pqIds  = $saq->responses->pluck('project_question_id')->filter()->unique()->values();
            $allPqs = ProjectQuestion::whereIn('id', $pqIds)->get()->keyBy('id');
        }

        // 透過 source_bank_question_id 載入題庫問題的 tag_slugs 與 sasb_topic
        $bankQIds = $allPqs->pluck('source_bank_question_id')->filter()->unique()->values();
        $bankQMap = SAQQuestion::with(['questionTags', 'sasbTopic'])
            ->whereIn('id', $bankQIds)
            ->get()
            ->keyBy('id');

        // 以 project_question_id 索引回覆
        $responseMap = $saq->responses->keyBy('project_question_id');

        $responses = $allPqs->map(function ($pq) use ($responseMap, $bankQMap) {
            $r  = $responseMap->get($pq->id);
            $bq = $pq->source_bank_question_id ? $bankQMap->get($pq->source_bank_question_id) : null;
            return [
                'question_id'        => $pq->id,
                'source_question_id' => $pq->source_bank_question_id,
                'weight'             => $pq->weight ?? 1.0,
                'answer'             => $r?->answer,
                'answer_options'     => $r?->answer_options ?? [],
                'sasb_topic'         => $bq?->sasbTopic?->topic_code ?? null,
                'tag_slugs'          => $bq?->questionTags->pluck('slug')->all() ?? [],
                // saq-scoring-quality: 計分 metadata
                'scoring_direction'  => $pq->scoring_direction ?? 'positive',
                'scoring_type'       => $pq->scoring_type,
                'option_scores'      => $pq->option_scores,
                'options'            => $pq->options,
            ];
        })->values()->toArray();

        // 使用 Docker 內部主機名（LARAVEL_INTERNAL_URL），讓 Celery worker 在容器網路內回呼
        $apiBaseUrl  = rtrim(env('LARAVEL_INTERNAL_URL', 'http://esgchain-api:8080'), '/');
        $callbackUrl = "{$apiBaseUrl}/api/v1/internal/saqs/{$saq->id}/score-callback";

        $aiUrl = rtrim(config('services.ai.url', env('AI_SERVICE_URL', 'http://esgchain-ai:8000')), '/');

        // E4 客觀資料組裝
        $countryDefenseScore = $this->resolveCountryDefenseScore($saq->supplier?->country ?? null);
        $e4ObjectiveRatio    = $saq->project?->series?->e4_objective_ratio ?? 0.40;
        $saqCompleted        = $saq->status === 'submitted' || $saq->score !== null;

        try {
            $response = Http::timeout(30)->post("{$aiUrl}/ai/v1/scoring/celery/saq-scoring", [
                'saq_id'             => $saq->id,
                'responses'          => $responses,
                'scoring_framework'       => $saq->project?->template?->scoring_framework ?? null,
                'sasb_industry_code'      => $saq->supplier?->sasbIndustry?->code ?? null,
                'series_pillar_weights'      => $this->resolveSlugWeights($saq->project?->series?->pillar_weights),
                'series_grade_thresholds'    => $saq->project?->series?->grade_thresholds ?? null,
                'series_dim_weights'         => $saq->project?->series?->dim_weights ?? null,
                'series_e4_objective_ratio'  => $saq->project?->series?->e4_objective_ratio ?? null,
                'country_defense_score'      => $countryDefenseScore,
                'e4_objective_ratio'         => $e4ObjectiveRatio,
                'saq_completed'              => $saqCompleted,
                'callback_url'            => $callbackUrl,
            ]);

            if ($response->successful()) {
                $taskId = $response->json('task_id');
                $saq->update(['scoring_job_id' => $taskId]);
            } else {
                Log::warning("SAQ 計分觸發失敗", ['saq_id' => $this->saqId, 'http_status' => $response->status()]);
            }
        } catch (\Throwable $e) {
            Log::warning("SAQ 計分 Job 例外：{$e->getMessage()}", ['saq_id' => $this->saqId]);
        }
    }

    /**
     * 查詢供應商所在國家的 country_risk_ratings，計算 country_defense_score。
     * 公式：100 − (rating − 1) / 4 × 100，rating = mean(sub_scores) fallback geo_risk。
     * 查無資料時回傳 null（AI 側走純 SAQ 路徑）。
     */
    private function resolveCountryDefenseScore(?string $countryCode): ?float
    {
        if (!$countryCode) return null;

        $rating = CountryRiskRating::where('country_code', strtoupper($countryCode))->first();
        if (!$rating) return null;

        $sub = $rating->sub_scores;
        if (!empty($sub) && is_array($sub)) {
            $values = array_filter(array_values($sub), fn($v) => is_numeric($v));
            $riskRating = count($values) > 0 ? array_sum($values) / count($values) : (float) $rating->geo_risk;
        } else {
            $riskRating = (float) $rating->geo_risk;
        }

        return round(100 - ($riskRating - 1) / 4 * 100, 2);
    }

    /**
     * pillar_weights key 由 UUID 轉換為 L2 slug，讓 AI 計分服務可直接比對。
     * 若 key 不是 UUID（舊格式 slug），直接原樣回傳。
     */
    private function resolveSlugWeights(?array $pillarWeights): ?array
    {
        if (empty($pillarWeights)) return $pillarWeights;

        $uuidKeys = array_filter(array_keys($pillarWeights), fn($k) => preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $k
        ));

        if (empty($uuidKeys)) return $pillarWeights;

        $slugMap = QuestionTag::whereIn('id', $uuidKeys)->pluck('slug', 'id');
        $resolved = [];
        foreach ($pillarWeights as $key => $weight) {
            $resolved[$slugMap[$key] ?? $key] = $weight;
        }
        return $resolved;
    }
}
