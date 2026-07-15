<?php

namespace App\Services\SAQ;

use App\Models\SAQ;
use App\Models\SaqScoreSnapshot;
use Illuminate\Support\Carbon;

class SaqScoreSnapshotService
{
    /**
     * 建立計分快照（append-only）
     *
     * @param SAQ         $saq
     * @param string      $trigger        submit|weight_updated|reviewer_override|re_review
     * @param string|null $triggeredBy    操作者 user_id；AI 自動計分時為 null
     * @param float|null  $scoreOverride  覆核分場景：傳入 final_score 取代 saq->score
     * @param string|null $gradeOverride  覆核分場景：傳入 final_grade 取代 saq->grade
     */
    public function create(
        SAQ $saq,
        string $trigger,
        ?string $triggeredBy = null,
        ?float $scoreOverride = null,
        ?string $gradeOverride = null,
    ): SaqScoreSnapshot {
        return SaqScoreSnapshot::create([
            'saq_id'           => $saq->id,
            'score'            => $scoreOverride ?? $saq->score,
            'grade'            => $gradeOverride ?? $saq->grade,
            'score_e'          => $saq->score_e ?? null,
            'score_s'          => $saq->score_s ?? null,
            'score_g'          => $saq->score_g ?? null,
            'scoring_model_id' => $saq->scoring_model_id ?? null,
            'trigger'          => $trigger,
            'triggered_by'     => $triggeredBy,
            'scored_at'        => Carbon::now(),
        ]);
    }
}
