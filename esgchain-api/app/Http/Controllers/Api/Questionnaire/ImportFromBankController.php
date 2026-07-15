<?php

namespace App\Http\Controllers\Api\Questionnaire;

use App\Http\Controllers\Controller;
use App\Models\QuestionTag;
use App\Models\QuestionTagAssignment;
use App\Models\SAQQuestion;
use App\Models\SAQTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportFromBankController extends Controller
{
    public function __invoke(Request $request, SAQTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'question_ids'   => ['required', 'array', 'min:1'],
            'question_ids.*' => ['uuid'],
        ]);

        $bankQuestions = SAQQuestion::with('tagAssignments')
            ->whereIn('id', $validated['question_ids'])
            ->whereNull('template_id')
            ->get()
            ->keyBy('id');

        $missing = array_diff($validated['question_ids'], $bankQuestions->keys()->toArray());
        if (!empty($missing)) {
            return response()->json([
                'success' => false,
                'message' => '部分 ID 不是題庫題目：' . implode(', ', array_map(fn($id) => substr($id, 0, 8) . '...', $missing)),
            ], 422);
        }

        // 應用層框架 TAG 驗證：若範本有 scoring_framework，題目至少需有一個符合的 TAG
        $framework = $template->scoring_framework;
        if ($framework) {
            $incompatible = [];
            foreach ($bankQuestions as $bq) {
                $hasMatch = $bq->tagAssignments->contains(function ($a) use ($framework) {
                    $tag = QuestionTag::find($a->tag_id);
                    return $tag && $tag->l1_domain === $framework && !$tag->deprecated_at;
                });
                if (!$hasMatch) {
                    $incompatible[] = mb_substr($bq->question_text, 0, 30) . '...';
                }
            }
            if (!empty($incompatible)) {
                return response()->json([
                    'success' => false,
                    'message' => "以下題目的 TAG 領域與範本框架（{$framework}）不相符，無法匯入：\n" . implode("\n", $incompatible),
                ], 422);
            }
        }

        $maxOrder = SAQQuestion::where('template_id', $template->id)->max('order') ?? 0;
        $created = [];
        $i = 1;

        foreach ($validated['question_ids'] as $bankQuestionId) {
            $bq = $bankQuestions[$bankQuestionId];

            // 計算 framework_pillar：取第一個符合框架 l1_domain 的 TAG 的 l2_pillar
            $frameworkPillar = null;
            if ($framework) {
                foreach ($bq->tagAssignments as $a) {
                    $tag = QuestionTag::find($a->tag_id);
                    if ($tag && $tag->l1_domain === $framework && !$tag->deprecated_at) {
                        $frameworkPillar = $tag->l2_pillar;
                        break;
                    }
                }
            }

            $copy = SAQQuestion::create([
                'template_id'             => $template->id,
                'question_text'           => $bq->question_text,
                'question_type'           => $bq->question_type,
                'options'                 => $bq->options,
                'weight'                  => $bq->weight,
                'order'                   => $maxOrder + $i,
                'is_required'             => $bq->is_required,
                'sasb_topic_id'           => $bq->sasb_topic_id,
                'sasb_metric_code'        => $bq->sasb_metric_code,
                'tags'                    => $bq->tags,
                'source_bank_question_id' => $bq->id,
                'framework_pillar'        => $frameworkPillar,
            ]);

            // 快照隔離：複製題庫原題的 tag_assignments 至快照副本
            foreach ($bq->tagAssignments as $assignment) {
                QuestionTagAssignment::firstOrCreate([
                    'question_id' => $copy->id,
                    'tag_id'      => $assignment->tag_id,
                ]);
            }

            $created[] = $copy->load('questionTags');
            $i++;
        }

        return response()->json([
            'success' => true,
            'data'    => $created,
            'message' => "已從題庫匯入 " . count($created) . " 道題目",
        ], 201);
    }
}
