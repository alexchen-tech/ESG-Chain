<?php

namespace App\Services\SAQ;

use App\Models\ProjectQuestion;
use App\Models\SAQ;
use App\Models\SAQQuestion;
use App\Models\SAQResponseReview;
use Illuminate\Support\Collection;

/**
 * 題目層覆核服務
 *
 * 使用 Mode A（E/S/G 三維加權）重算 final_score：
 * - 有覆核分者優先使用 reviewer_score（0–100）
 * - 否則從 saq_responses.raw_score / q.weight 還原 answer_score
 * - category_avg(X) = Σ(effective_score × q.weight) / Σ(q.weight)
 * - final_score = avg_E×w_E + avg_S×w_S + avg_G×w_G
 */
class SaqReviewerScoreService
{
    // Mode A 預設 ESG 權重（與 AI 端一致；優先使用 SAQ.scoring_model_id 對應的實際權重，未實作時 fallback）
    private const DEFAULT_WEIGHTS = ['E' => 0.40, 'S' => 0.35, 'G' => 0.25];
    private const DEFAULT_THRESHOLDS = ['A' => 80.0, 'B' => 60.0, 'C' => 40.0, 'D' => 20.0];

    // slug prefix → ESG 分類（與 AI scoring_service.py 保持一致）
    private const SLUG_TO_ESG = [
        'esg.e.'                => 'E',
        'esg.s.'                => 'S',
        'esg.g.'                => 'G',
        'iso20400.org_gov.'     => 'G',
        'iso20400.human_rights.' => 'S',
        'iso20400.labor.'       => 'S',
        'iso20400.environment.' => 'E',
        'iso20400.fair_ops.'    => 'G',
        'iso20400.consumer.'    => 'S',
        'iso20400.community.'   => 'S',
        'geo_risk.political.'   => 'G',
        'geo_risk.logistics.'   => 'G',
        'product_compliance.'   => 'G',
    ];

    public function __construct(
        private readonly SaqScoreSnapshotService $snapshotService,
    ) {}

    /**
     * 批次提交覆核分（upsert），重算 final_score，建立 snapshot
     *
     * @param SAQ    $saq
     * @param array  $reviews   [{project_question_id, reviewer_score, reason}]
     * @param string $reviewerId
     */
    public function submitReviews(SAQ $saq, array $reviews, string $reviewerId): SAQ
    {
        if ($saq->status === 'finalized') {
            abort(422, json_encode([
                'success'    => false,
                'error_code' => 'BUSINESS_ERROR',
                'message'    => '問卷已終結，不允許修改覆核分',
            ]));
        }

        if ($saq->status !== 'under_review' && $saq->status !== 're_review') {
            abort(422, json_encode([
                'success'    => false,
                'error_code' => 'BUSINESS_ERROR',
                'message'    => "問卷狀態「{$saq->status}」不允許提交覆核分",
            ]));
        }

        foreach ($reviews as $review) {
            SAQResponseReview::updateOrCreate(
                [
                    'saq_id'              => $saq->id,
                    'project_question_id' => $review['project_question_id'],
                ],
                [
                    'reviewer_id'    => $reviewerId,
                    'reviewer_score' => $review['reviewer_score'],
                    'reason'         => $review['reason'] ?? null,
                ]
            );
        }

        $saq = $this->recalculateFinalScore($saq);

        $this->snapshotService->create(
            $saq,
            'reviewer_override',
            $reviewerId,
            $saq->final_score,
            $saq->final_grade,
        );

        return $saq->fresh(['responseReviews']);
    }

    /**
     * Mode A 重算 final_score / final_grade
     */
    public function recalculateFinalScore(SAQ $saq): SAQ
    {
        if (!$saq->project_id) {
            return $saq;
        }

        // 載入 project_questions（含 weight 與 source_bank_question_id）
        $projectQuestions = ProjectQuestion::where('project_id', $saq->project_id)->get();
        if ($projectQuestions->isEmpty()) {
            return $saq;
        }

        // 載入 bank questions 以取得 tag_slugs（用於 E/S/G 分類）
        $bankQIds = $projectQuestions->pluck('source_bank_question_id')->filter()->unique();
        $bankQMap = SAQQuestion::with('questionTags')
            ->whereIn('id', $bankQIds)
            ->get()
            ->keyBy('id');

        // 載入覆核分
        $reviewMap = $saq->responseReviews()->get()->keyBy('project_question_id');

        // 載入 AI 原始 raw_score
        $rawScoreMap = $saq->responses()->whereNotNull('raw_score')
            ->get(['project_question_id', 'raw_score'])
            ->keyBy('project_question_id');

        // Mode A 計算
        $categoryItems = ['E' => [], 'S' => [], 'G' => []];

        foreach ($projectQuestions as $pq) {
            $weight = (float) ($pq->weight ?? 1.0);

            // 決定 effective_score（覆核分優先，否則還原 AI 原始分）
            if ($reviewMap->has($pq->id)) {
                $effectiveScore = (float) $reviewMap->get($pq->id)->reviewer_score;
            } elseif ($rawScoreMap->has($pq->id) && $weight > 0) {
                $effectiveScore = (float) $rawScoreMap->get($pq->id)->raw_score / $weight;
                $effectiveScore = max(0.0, min(100.0, $effectiveScore));
            } else {
                $effectiveScore = 0.0;
            }

            // E/S/G 分類
            $cat = 'G';
            $bq  = $pq->source_bank_question_id ? $bankQMap->get($pq->source_bank_question_id) : null;
            if ($bq) {
                foreach ($bq->questionTags as $tag) {
                    $cat = $this->slugToEsg($tag->slug);
                    if ($cat !== 'G') break; // 取第一個 non-G 分類
                }
            }

            $categoryItems[$cat][] = [$effectiveScore, $weight];
        }

        $weights    = self::DEFAULT_WEIGHTS;
        $thresholds = self::DEFAULT_THRESHOLDS;

        $avgE = $this->weightedAvg($categoryItems['E']);
        $avgS = $this->weightedAvg($categoryItems['S']);
        $avgG = $this->weightedAvg($categoryItems['G']);

        $finalScore = round(
            $avgE * $weights['E'] + $avgS * $weights['S'] + $avgG * $weights['G'],
            2
        );

        $finalGrade = 'E';
        foreach (['A', 'B', 'C', 'D'] as $g) {
            if ($finalScore >= $thresholds[$g]) {
                $finalGrade = $g;
                break;
            }
        }

        $saq->update([
            'final_score' => $finalScore,
            'final_grade' => $finalGrade,
        ]);

        return $saq->fresh();
    }

    private function weightedAvg(array $items): float
    {
        if (empty($items)) return 0.0;
        $totalW = array_sum(array_column($items, 1));
        if ($totalW === 0.0) return 0.0;
        return array_sum(array_map(fn($i) => $i[0] * $i[1], $items)) / $totalW;
    }

    private function slugToEsg(string $slug): string
    {
        foreach (self::SLUG_TO_ESG as $prefix => $cat) {
            if (str_starts_with($slug, $prefix)) return $cat;
        }
        return 'G';
    }
}
