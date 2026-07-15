<?php

namespace App\Services\SAQ;

use App\Models\AssessmentSeriesWeight;
use App\Models\ProjectQuestion;
use App\Models\SaqProject;
use App\Models\SAQTemplate;
use Illuminate\Support\Str;

class ProjectQuestionService
{
    public function snapshot(SaqProject $project, SAQTemplate $template): void
    {
        $questions = $template->questions()->with('tagAssignments.tag')->get();

        // 從範本權重正規化 → project_questions.weight（加總 = 1.0）
        $rawWeights  = $questions->pluck('weight');
        $totalWeight = $rawWeights->filter()->sum();
        $n           = $questions->count();
        $useUniform  = ($totalWeight <= 0 || $n === 0);

        $rows = $questions->map(function ($q) use ($project, $totalWeight, $n, $useUniform) {
            $tags = $q->tagAssignments->map(fn($a) => [
                'id'        => $a->tag?->id,
                'name'      => $a->tag?->name,
                'l1_domain' => $a->tag?->l1_domain ?? null,
            ])->filter(fn($t) => $t['id'])->values()->toArray();

            // 正規化：範本有 weight → weight/total；否則均分 1/N
            $normalizedWeight = $useUniform
                ? round(1.0 / $n, 6)
                : round(($q->weight ?? 0) / $totalWeight, 6);

            return [
                'id'                           => (string) Str::uuid(),
                'project_id'                   => $project->id,
                'order'                         => $q->order,
                'question_text'                 => $q->question_text,
                'question_type'                 => $q->question_type,
                'options'                       => $q->options ? json_encode($q->options) : null,
                'weight'                        => $normalizedWeight,
                'is_required'                   => $q->is_required ? 1 : 0,
                'sasb_topic_id'                 => $q->sasb_topic_id,
                'sasb_metric_code'              => $q->sasb_metric_code,
                'tags'                          => json_encode($tags),
                'source_bank_question_id'       => $q->source_bank_question_id,
                'source_template_question_id'   => $q->id,
                'created_at'                    => now(),
                'updated_at'                    => now(),
            ];
        })->toArray();

        if (!empty($rows)) {
            ProjectQuestion::insert($rows);
        }

        // 若 project 屬於 series，從 series weight schema 填入 weight
        if ($project->series_id) {
            $this->applySeriesWeights($project);
        }
    }

    /**
     * 專案建立後更新題目權重（前端傳入原始值，後端正規化後存入）
     * 允許時機：project 未 closed
     * @param array<string,float> $rawWeights  [project_question_id => raw_weight]
     */
    public function updateWeights(SaqProject $project, array $rawWeights): void
    {
        if ($project->status === 'closed') {
            throw new \DomainException('已結案的專案不可調整題目權重');
        }

        $total = array_sum($rawWeights);
        if ($total <= 0) {
            throw new \InvalidArgumentException('權重總和必須大於 0');
        }

        foreach ($rawWeights as $pqId => $raw) {
            ProjectQuestion::where('id', $pqId)
                ->where('project_id', $project->id)
                ->update(['weight' => round($raw / $total, 6)]);
        }
    }

    private function applySeriesWeights(SaqProject $project): void
    {
        $weights = AssessmentSeriesWeight::where('series_id', $project->series_id)
            ->get()
            ->keyBy('source_template_question_id');

        if ($weights->isEmpty()) {
            return;
        }

        $projectQuestions = ProjectQuestion::where('project_id', $project->id)
            ->whereNotNull('source_template_question_id')
            ->get();

        foreach ($projectQuestions as $pq) {
            $w = $weights->get($pq->source_template_question_id);
            if ($w !== null) {
                $pq->update(['weight' => $w->weight]);
            }
        }
    }
}
