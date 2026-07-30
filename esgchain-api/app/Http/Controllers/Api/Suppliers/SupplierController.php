<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Http\Controllers\Controller;
use App\Models\RiskAssessment;
use App\Models\Supplier;
use App\Services\Suppliers\SupplierService;
use App\Services\Suppliers\SupplierTimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $service,
        private readonly SupplierTimelineService $timelineService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'status', 'onboarding_stage', 'tier', 'q', 'supplier_group_id', 'country_code', 'sasb_industry_id',
            'risk_dim', 'risk_probability', 'risk_impact',
            'page', 'per_page',
        ]);
        $includeRisk = $request->boolean('include_risk');
        if ($includeRisk) $filters['include_risk'] = true;

        $paginated = $this->service->list($filters);

        $items = collect($paginated->items())->map(function ($supplier) use ($includeRisk) {
            $arr = $supplier->toArray();
            if ($includeRisk) {
                $ra = $supplier->latestRiskAssessment;
                $arr['latest_risk'] = $ra ? [
                    'assessed_at' => $ra->assessed_at,
                    'axes' => [
                        'axis1' => ['score' => $ra->axis1_score, 'level' => RiskAssessment::axisToLevel($ra->axis1_score)],
                        'axis2' => ['score' => $ra->axis2_score, 'level' => RiskAssessment::axisToLevel($ra->axis2_score)],
                        'axis3' => ['score' => $ra->axis3_score, 'level' => RiskAssessment::axisToLevel($ra->axis3_score)],
                    ],
                ] : null;
            }
            return $arr;
        })->all();

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
            'message' => '',
        ]);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $supplier->load(['group', 'sasbIndustry', 'contacts', 'statusHistories' => function ($q) {
                $q->orderBy('created_at', 'desc');
            }]),
            'message' => '',
        ]);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        // name/code/country_code/industry/tier/vat_number/erp_vendor_codes/spend_amount/address/website
        // 為 ERP 匯入的廠商主檔資料，一律唯讀，不可從一般編輯 API 修改；
        // 僅 ESG-Chain 自有分類（industry_group/sasb_industry_id/group_id）可編輯。
        // 供應商「活躍狀態」（onboarding_stage）另有專屬的 onboarding-transition 端點，
        // 不在此 API 開放。
        $validated = $request->validate([
            'industry_group'   => ['sometimes', 'nullable', 'in:製造業,勞動密集製造,農林漁業,科技電子,物流倉儲,原物料化工,服務業'],
            'sasb_industry_id' => ['sometimes', 'nullable', 'uuid', 'exists:sasb_industries,id'],
            'group_id'         => ['sometimes', 'nullable', 'uuid', 'exists:supplier_groups,id'],
        ]);

        $updated = $this->service->update($supplier, $validated);

        return response()->json([
            'success' => true,
            'data' => $updated,
            'message' => '供應商更新成功',
        ]);
    }

    public function transitionOnboarding(Request $request, Supplier $supplier): JsonResponse
    {
        $stages = implode(',', Supplier::ONBOARDING_STAGES);
        $validated = $request->validate([
            'onboarding_stage' => ['required', 'string', "in:{$stages}"],
            'reason'           => ['nullable', 'string'],
        ]);

        $supplier->transitionOnboardingStage(
            $validated['onboarding_stage'],
            $validated['reason'] ?? null,
            $request->user()->id
        );

        return response()->json([
            'success' => true,
            'data'    => $supplier->fresh()->load(['group', 'sasbIndustry', 'contacts']),
            'message' => '供應商入選階段已更新',
        ]);
    }

    public function timeline(Supplier $supplier): JsonResponse
    {
        $data = $this->timelineService->getTimeline($supplier->id);

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => '',
        ]);
    }

    public function riskSummary(Supplier $supplier): JsonResponse
    {
        $latest = RiskAssessment::where('supplier_id', $supplier->id)
            ->orderBy('assessed_at', 'desc')
            ->first();

        return response()->json([
            'success' => true,
            'data'    => $latest
                ? $latest->toRiskSummary()
                : ['supplier_id' => $supplier->id, 'e' => null, 's' => null, 'g' => null, 'gp' => null, 'assessed_at' => null],
            'message' => $latest ? '' : '尚無風險評估資料',
        ]);
    }

    public function updateAxis3Risk(Request $request, Supplier $supplier): JsonResponse
    {
        $data = $request->validate(['score' => 'required|numeric|min:0|max:100']);

        $latest = RiskAssessment::where('supplier_id', $supplier->id)
            ->orderBy('assessed_at', 'desc')
            ->first();

        if ($latest) {
            $latest->update(['axis3_score' => $data['score']]);
        } else {
            RiskAssessment::create([
                'supplier_id'  => $supplier->id,
                'axis3_score'  => $data['score'],
                'assessed_at'  => now(),
                'assessed_by'  => 'manual',
                'is_auto'      => false,
            ]);
        }

        return response()->json(['success' => true, 'message' => '地緣產業風險已更新']);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->service->delete($supplier);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => '供應商已刪除',
        ]);
    }
}
