<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\QuestionTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagLibraryController extends Controller
{
    private const VALID_DOMAINS = ['ESG', 'ISO20400', 'ISO26000', 'ISO28000', 'Geo-Risk', 'Product-Compliance'];

    public function index(Request $request): JsonResponse
    {
        $query = QuestionTag::query();

        if ($request->l1) {
            $query->where('l1_domain', $request->l1);
        }
        if ($request->l2) {
            $query->where('l2_pillar', $request->l2);
        }
        if ($request->l3) {
            $query->where('l3_topic', 'like', '%' . $request->l3 . '%');
        }
        if (!$request->boolean('include_deprecated', false)) {
            $query->whereNull('deprecated_at');
        }

        $tags = $query->orderBy('l1_domain')->orderBy('l2_pillar')->orderBy('sort_order')->get();

        return response()->json(['success' => true, 'data' => $tags]);
    }

    public function tree(): JsonResponse
    {
        $tags = QuestionTag::whereNull('deprecated_at')
            ->orderBy('l1_domain')
            ->orderBy('l2_pillar')
            ->orderBy('sort_order')
            ->get();

        $tree = [];
        foreach ($tags as $tag) {
            $l1 = $tag->l1_domain;
            $l2 = $tag->l2_pillar;

            if (!isset($tree[$l1])) {
                $tree[$l1] = ['l1_domain' => $l1, 'pillars' => []];
            }
            if (!isset($tree[$l1]['pillars'][$l2])) {
                $tree[$l1]['pillars'][$l2] = ['l2_pillar' => $l2, 'topics' => []];
            }
            // L2 節點（l3_topic='General'）僅用於建立 pillar 分類，不列入 L3 調查主題
            if ($tag->l3_topic !== 'General') {
                $tree[$l1]['pillars'][$l2]['topics'][] = $tag;
            }
        }

        // 重組為 array 結構
        $result = array_values(array_map(function ($domain) {
            $domain['pillars'] = array_values(array_map(function ($pillar) {
                $pillar['topics'] = array_values($pillar['topics']);
                return $pillar;
            }, $domain['pillars']));
            return $domain;
        }, $tree));

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'l1_domain'          => ['required', 'string', 'in:' . implode(',', self::VALID_DOMAINS)],
            'l2_pillar'          => ['required', 'string', 'max:40'],
            'l3_topic'           => ['required', 'string', 'max:60'],
            'slug'               => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9_.]+$/', 'unique:question_tags,slug'],
            'label_zh'           => ['nullable', 'string', 'max:100'],
            'label_en'           => ['nullable', 'string', 'max:100'],
            'scoring_engine_key' => ['nullable', 'string', 'max:60'],
            'sort_order'         => ['nullable', 'integer'],
        ]);

        // slug 自動生成（若未提供）
        if (empty($validated['slug'])) {
            $l1Key = strtolower(str_replace(['-', ' '], '_', $validated['l1_domain']));
            $l2Key = strtolower(str_replace(['-', ' ', '/'], '_', $validated['l2_pillar']));
            $l3Key = strtolower(str_replace(['-', ' ', '（', '）', '(', ')'], '_', $validated['l3_topic']));
            $l3Key = preg_replace('/_+/', '_', trim($l3Key, '_'));
            $validated['slug'] = "{$l1Key}.{$l2Key}.{$l3Key}";

            // 確保唯一性
            if (QuestionTag::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] .= '_' . Str::random(4);
            }
        }

        $tag = QuestionTag::create($validated);

        return response()->json(['success' => true, 'data' => $tag], 201);
    }

    public function update(Request $request, QuestionTag $tag): JsonResponse
    {
        $validated = $request->validate([
            'l1_domain'          => ['sometimes', 'string', 'in:' . implode(',', self::VALID_DOMAINS)],
            'l2_pillar'          => ['sometimes', 'string', 'max:40'],
            'l3_topic'           => ['sometimes', 'string', 'max:60'],
            'label_zh'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'label_en'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'scoring_engine_key' => ['sometimes', 'nullable', 'string', 'max:60'],
            'sort_order'         => ['sometimes', 'integer'],
        ]);

        // slug 欄位強制忽略（不可變）
        unset($validated['slug']);

        $tag->update($validated);

        return response()->json(['success' => true, 'data' => $tag->fresh()]);
    }

    public function deprecate(QuestionTag $tag): JsonResponse
    {
        $tag->update(['deprecated_at' => now()]);

        return response()->json(['success' => true, 'message' => '標籤已停用']);
    }

    public function restore(QuestionTag $tag): JsonResponse
    {
        $tag->update(['deprecated_at' => null]);

        return response()->json(['success' => true, 'message' => '標籤已恢復啟用']);
    }

    // 題目的 tag assignments CRUD
    public function questionTags(string $questionId): JsonResponse
    {
        $tags = QuestionTag::whereHas('assignments', fn($q) => $q->where('question_id', $questionId))
            ->orderBy('l1_domain')->orderBy('l2_pillar')->orderBy('sort_order')
            ->get();

        return response()->json(['success' => true, 'data' => $tags]);
    }

    public function addQuestionTag(Request $request, string $questionId): JsonResponse
    {
        $validated = $request->validate([
            'tag_id' => ['required', 'uuid', 'exists:question_tags,id'],
        ]);

        \App\Models\QuestionTagAssignment::firstOrCreate([
            'question_id' => $questionId,
            'tag_id'      => $validated['tag_id'],
        ]);

        return response()->json(['success' => true, 'message' => '標籤已新增'], 201);
    }

    public function removeQuestionTag(string $questionId, string $tagId): JsonResponse
    {
        \App\Models\QuestionTagAssignment::where('question_id', $questionId)
            ->where('tag_id', $tagId)
            ->delete();

        return response()->json(['success' => true, 'message' => '標籤已移除']);
    }
}
