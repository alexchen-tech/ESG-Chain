<?php

namespace App\Http\Controllers\Api\Risk;

use App\Http\Controllers\Controller;
use App\Models\RiskAssessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RiskAssessmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $paginated = RiskAssessment::with('supplier')
            ->when($request->supplier_id, fn($q, $v) => $q->where('supplier_id', $v))
            ->orderBy('assessed_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
            'message' => '',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // 系統採問卷驅動立場：風險評估只能由 SAQ 完成後自動推導，不接受手動建立
        abort(403, '風險評估僅由 SAQ 問卷完成後自動推導，不支援手動建立。請完成對應供應商的問卷後由系統自動產生。');
    }

    /** @deprecated 保留方法簽名供路由解析，實際已被 store() 攔截 */
    private function _storeDeprecated(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id'    => ['required', 'uuid', 'exists:suppliers,id'],
            'e_probability'  => ['required', 'integer', 'min:1', 'max:5'],
            'e_impact'       => ['required', 'integer', 'min:1', 'max:5'],
            's_probability'  => ['required', 'integer', 'min:1', 'max:5'],
            's_impact'       => ['required', 'integer', 'min:1', 'max:5'],
            'g_probability'  => ['required', 'integer', 'min:1', 'max:5'],
            'g_impact'       => ['required', 'integer', 'min:1', 'max:5'],
            'gp_probability' => ['required', 'integer', 'min:1', 'max:5'],
            'gp_impact'      => ['required', 'integer', 'min:1', 'max:5'],
            'notes'          => ['nullable', 'string'],
        ]);

        $today = Carbon::today();

        // 同一供應商同日只保留一筆手動評估（upsert，更新既有記錄）
        $existing = RiskAssessment::where('supplier_id', $validated['supplier_id'])
            ->whereDate('assessed_at', $today)
            ->whereNull('source_saq_id')
            ->first();

        if ($existing) {
            $existing->update(array_merge($validated, [
                'assessed_at' => Carbon::now(),
                'assessed_by' => $request->user()->id,
            ]));
            return response()->json([
                'success' => true,
                'data'    => $existing->fresh(),
                'message' => '今日風險評估已更新',
            ]);
        }

        $assessment = RiskAssessment::create(array_merge($validated, [
            'assessed_at' => Carbon::now(),
            'assessed_by' => $request->user()->id,
        ]));

        return response()->json([
            'success' => true,
            'data'    => $assessment,
            'message' => '風險評估已建立',
        ], 201);
    }

    public function update(Request $request, RiskAssessment $riskAssessment): JsonResponse
    {
        $validated = $request->validate([
            'e_probability'  => ['sometimes', 'integer', 'min:1', 'max:5'],
            'e_impact'       => ['sometimes', 'integer', 'min:1', 'max:5'],
            's_probability'  => ['sometimes', 'integer', 'min:1', 'max:5'],
            's_impact'       => ['sometimes', 'integer', 'min:1', 'max:5'],
            'g_probability'  => ['sometimes', 'integer', 'min:1', 'max:5'],
            'g_impact'       => ['sometimes', 'integer', 'min:1', 'max:5'],
            'gp_probability' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'gp_impact'      => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'notes'          => ['sometimes', 'nullable', 'string'],
        ]);

        $riskAssessment->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $riskAssessment->fresh(),
            'message' => '風險評估已更新',
        ]);
    }
}
