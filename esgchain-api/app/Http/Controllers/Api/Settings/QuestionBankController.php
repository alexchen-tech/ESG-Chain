<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\QuestionTag;
use App\Models\SAQQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionBankController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SAQQuestion::bank()->with(['sasbTopic', 'questionTags']);

        if ($request->keyword) {
            $query->where('question_text', 'like', '%' . $request->keyword . '%');
        }

        // 三層標籤篩選（透過 question_tag_assignments JOIN）
        if ($request->l1 || $request->l2 || $request->l3) {
            $query->whereHas('questionTags', function ($q) use ($request) {
                if ($request->l1) $q->where('l1_domain', $request->l1);
                if ($request->l2) $q->where('l2_pillar', $request->l2);
                if ($request->l3) $q->where('slug', $request->l3);
            });
        }

        // 合規範疇篩選
        if ($request->compliance_domain) {
            $query->whereJsonContains('compliance_domains', $request->compliance_domain);
        }

        $perPage = min((int) ($request->per_page ?? 20), 100);
        $paginated = $query->orderBy('order')->paginate($perPage, ['*'], 'page', $request->page ?? 1);

        $data = collect($paginated->items())->map(fn($q) => array_merge($q->toArray(), [
            'usage_count'   => $q->usageCount(),
            'question_tags' => $q->questionTags,
        ]));

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
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
            'tags'                   => ['nullable', 'array'],
            'tags.*'                 => ['string', 'exists:question_tags,slug'],
            'compliance_domains'     => ['nullable', 'array'],
            'compliance_domains.*'   => ['string'],
            'tag_ids'                => ['nullable', 'array'],
            'tag_ids.*'              => ['uuid', 'exists:question_tags,id'],
        ]);

        $tagIds = $validated['tag_ids'] ?? [];
        unset($validated['tag_ids']);

        // 僅在未明確傳入 compliance_domains 時，從 tags 自動同步
        if (!empty($validated['tags']) && !array_key_exists('compliance_domains', $validated)) {
            $validated['compliance_domains'] = SAQQuestion::syncComplianceDomains($validated['tags']);
        }

        $question = SAQQuestion::create(array_merge($validated, [
            'template_id'             => null,
            'source_bank_question_id' => null,
        ]));

        foreach ($tagIds as $tagId) {
            \App\Models\QuestionTagAssignment::firstOrCreate([
                'question_id' => $question->id,
                'tag_id'      => $tagId,
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => array_merge($question->load('questionTags')->toArray(), ['usage_count' => 0]),
        ], 201);
    }

    public function update(Request $request, SAQQuestion $question): JsonResponse
    {
        if ($question->template_id !== null) {
            return response()->json(['success' => false, 'message' => '此題目不是題庫題目'], 422);
        }

        $validated = $request->validate([
            'question_text'    => ['sometimes', 'string'],
            'options'          => ['sometimes', 'nullable', 'array'],
            'weight'           => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1'],
            'is_required'      => ['sometimes', 'boolean'],
            'sasb_topic_id'    => ['sometimes', 'nullable', 'uuid', 'exists:sasb_disclosure_topics,id'],
            'sasb_metric_code' => ['sometimes', 'nullable', 'string', 'max:40'],
            'tags'                   => ['sometimes', 'nullable', 'array'],
            'tags.*'                 => ['string', 'exists:question_tags,slug'],
            'compliance_domains'     => ['sometimes', 'nullable', 'array'],
            'compliance_domains.*'   => ['string'],
            'tag_ids'                => ['sometimes', 'nullable', 'array'],
            'tag_ids.*'              => ['uuid', 'exists:question_tags,id'],
        ]);

        $tagIds = $validated['tag_ids'] ?? null;
        unset($validated['tag_ids']);

        // 僅在未明確傳入 compliance_domains 時，從 tags 自動同步
        if (array_key_exists('tags', $validated) && !empty($validated['tags']) && !array_key_exists('compliance_domains', $validated)) {
            $validated['compliance_domains'] = SAQQuestion::syncComplianceDomains($validated['tags']);
        }

        $question->update($validated);

        // tag_ids 有傳入時，完整替換 assignments
        if ($tagIds !== null) {
            \App\Models\QuestionTagAssignment::where('question_id', $question->id)->delete();
            foreach ($tagIds as $tagId) {
                \App\Models\QuestionTagAssignment::create([
                    'question_id' => $question->id,
                    'tag_id'      => $tagId,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data'    => array_merge($question->fresh()->load('questionTags')->toArray(), ['usage_count' => $question->usageCount()]),
        ]);
    }

    public function destroy(SAQQuestion $question): JsonResponse
    {
        if ($question->template_id !== null) {
            return response()->json(['success' => false, 'message' => '此題目不是題庫題目'], 422);
        }

        $usageCount = $question->usageCount();
        $question->delete();

        return response()->json([
            'success' => true,
            'message' => $usageCount > 0
                ? "題庫題目已刪除（已有 {$usageCount} 個範本的副本不受影響）"
                : '題庫題目已刪除',
        ]);
    }
}
