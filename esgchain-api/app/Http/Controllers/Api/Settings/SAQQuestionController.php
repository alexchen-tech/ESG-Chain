<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\SAQQuestion;
use App\Models\SAQTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SAQQuestionController extends Controller
{
    public function index(SAQTemplate $template): JsonResponse
    {
        $questions = $template->questions()
            ->with(['questionTags', 'sasbTopic'])
            ->orderBy('order')
            ->get();

        return response()->json(['success' => true, 'data' => $questions]);
    }

    public function store(Request $request, SAQTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'question_text'    => ['required', 'string'],
            'question_type'    => ['required', 'in:single_choice,multiple_choice,text,number,boolean'],
            'options'          => ['nullable', 'array'],
            'weight'           => ['nullable', 'numeric', 'min:0', 'max:1'],
            'is_required'      => ['boolean'],
            'order'            => ['nullable', 'integer'],
            'sasb_topic_id'    => ['nullable', 'uuid', 'exists:sasb_disclosure_topics,id'],
            'sasb_metric_code' => ['nullable', 'string', 'max:40'],
            'tag_ids'          => ['nullable', 'array'],
            'tag_ids.*'        => ['uuid', 'exists:question_tags,id'],
            'compliance_domains' => ['nullable', 'array'],
        ]);

        $tagIds = $validated['tag_ids'] ?? [];
        unset($validated['tag_ids']);

        $validated['template_id'] = $template->id;
        $validated['order'] ??= ($template->questions()->max('order') ?? 0) + 1;

        $question = SAQQuestion::create($validated);

        if ($tagIds) {
            $question->questionTags()->sync($tagIds);
        }

        return response()->json([
            'success' => true,
            'data'    => $question->fresh()->load(['questionTags', 'sasbTopic']),
        ], 201);
    }

    public function update(Request $request, SAQTemplate $template, SAQQuestion $question): JsonResponse
    {
        if ($question->template_id !== $template->id) {
            return response()->json(['success' => false, 'message' => '題目不屬於此範本'], 404);
        }

        $validated = $request->validate([
            'question_text'      => ['sometimes', 'string'],
            'options'            => ['sometimes', 'nullable', 'array'],
            'weight'             => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1'],
            'is_required'        => ['sometimes', 'boolean'],
            'order'              => ['sometimes', 'integer'],
            'sasb_topic_id'      => ['sometimes', 'nullable', 'uuid', 'exists:sasb_disclosure_topics,id'],
            'sasb_metric_code'   => ['sometimes', 'nullable', 'string', 'max:40'],
            'tag_ids'            => ['sometimes', 'nullable', 'array'],
            'tag_ids.*'          => ['uuid', 'exists:question_tags,id'],
            'compliance_domains' => ['sometimes', 'nullable', 'array'],
        ]);

        $tagIds = $validated['tag_ids'] ?? null;
        unset($validated['tag_ids']);

        $question->update($validated);

        if (!is_null($tagIds)) {
            $question->questionTags()->sync($tagIds);
        }

        return response()->json([
            'success' => true,
            'data'    => $question->fresh()->load(['questionTags', 'sasbTopic']),
        ]);
    }

    public function destroy(SAQTemplate $template, SAQQuestion $question): JsonResponse
    {
        if ($question->template_id !== $template->id) {
            return response()->json(['success' => false, 'message' => '題目不屬於此範本'], 404);
        }

        $question->delete();

        return response()->json(['success' => true, 'message' => '題目已刪除']);
    }
}
