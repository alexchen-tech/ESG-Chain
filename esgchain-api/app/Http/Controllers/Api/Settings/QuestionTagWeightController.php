<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\QuestionTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionTagWeightController extends Controller
{
    /**
     * GET /api/v1/settings/tag-library/l2-nodes
     * 回傳所有 L2 節點（依 l1_domain 分組），含 id / slug / label_zh / default_weight。
     */
    public function index(): JsonResponse
    {
        $nodes = QuestionTag::l2()
            ->active()
            ->orderBy('l1_domain')
            ->orderBy('sort_order')
            ->get(['id', 'l1_domain', 'l2_pillar', 'slug', 'label_zh', 'default_weight', 'sort_order']);

        $grouped = $nodes->groupBy('l1_domain')->map(fn($items) => $items->values());

        return response()->json([
            'success' => true,
            'data'    => $grouped,
        ]);
    }

    /**
     * PUT /api/v1/settings/tag-library/l2-nodes/{tag}/weight
     * 更新單一 L2 節點的 default_weight。
     */
    public function updateWeight(Request $request, QuestionTag $tag): JsonResponse
    {
        if ($tag->l3_topic !== 'General' || $tag->l2_pillar === 'General') {
            return response()->json([
                'success' => false,
                'message' => '只有 L2 節點可設定 default_weight',
            ], 422);
        }

        $validated = $request->validate([
            'default_weight' => ['required', 'numeric', 'min:0.0001', 'max:1'],
        ]);

        $tag->update(['default_weight' => $validated['default_weight']]);

        return response()->json([
            'success' => true,
            'data'    => $tag->only(['id', 'slug', 'label_zh', 'default_weight']),
        ]);
    }
}
