<?php

namespace App\Http\Controllers\Api\TradeGoods;

use App\Http\Controllers\Controller;
use App\Models\TradeGood;
use App\Models\TradeGoodSupplier;
use App\Models\TradeGoodSupplierEmission;
use App\Services\TradeGoods\TradeGoodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeGoodController extends Controller
{
    public function __construct(private readonly TradeGoodService $service) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getList(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'product_code'      => ['nullable', 'string', 'max:100'],
            'hs_code'           => ['required', 'string', 'max:10'],
            'description'       => ['nullable', 'string'],
            'unit'              => ['nullable', 'string', 'max:20'],
            'quantity'          => ['nullable', 'numeric', 'min:0'],
            'unit_price'        => ['nullable', 'numeric', 'min:0'],
            'currency'          => ['nullable', 'string', 'size:3'],
            'material_group_id' => ['nullable', 'uuid', 'exists:material_groups,id'],
            'customer_id'       => ['nullable', 'uuid', 'exists:customers,id'],
        ]);

        $cbam = TradeGood::checkCbamApplicability($validated['hs_code']);
        $validated['is_cbam_applicable'] = $cbam['is_applicable'];
        $validated['cbam_category']      = $cbam['category'];

        $good = TradeGood::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $this->service->summarize($good->load('tradeGoodSuppliers.materialGroup', 'tradeGoodSuppliers.supplier.complianceDocs')),
            'message' => '貿易商品已建立' . ($cbam['is_applicable'] ? "（CBAM：{$cbam['category']}）" : ''),
        ], 201);
    }

    public function show(TradeGood $tradeGood): JsonResponse
    {
        $tradeGood->load('tradeGoodSuppliers.materialGroup', 'tradeGoodSuppliers.supplier.complianceDocs', 'materialGroup');
        return response()->json([
            'success' => true,
            'data'    => array_merge(
                $this->service->summarize($tradeGood),
                ['upstream_details' => $this->service->getUpstreamCompliance($tradeGood)]
            ),
        ]);
    }

    public function update(Request $request, TradeGood $tradeGood): JsonResponse
    {
        $validated = $request->validate([
            'name'         => ['sometimes', 'string', 'max:255'],
            'product_code' => ['sometimes', 'nullable', 'string', 'max:100'],
            'hs_code'      => ['sometimes', 'string', 'max:10'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'unit'         => ['sometimes', 'nullable', 'string'],
            'unit_price'   => ['sometimes', 'nullable', 'numeric'],
            'currency'     => ['sometimes', 'string', 'size:3'],
            'material_group_id' => ['sometimes', 'nullable', 'uuid', 'exists:material_groups,id'],
            'customer_id'       => ['sometimes', 'nullable', 'uuid', 'exists:customers,id'],
        ]);

        if (isset($validated['hs_code'])) {
            $cbam = TradeGood::checkCbamApplicability($validated['hs_code']);
            $validated['is_cbam_applicable'] = $cbam['is_applicable'];
            $validated['cbam_category']      = $cbam['category'];
        }

        $tradeGood->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $this->service->summarize($tradeGood->fresh()->load('tradeGoodSuppliers.materialGroup', 'tradeGoodSuppliers.supplier.complianceDocs')),
            'message' => '貿易商品已更新',
        ]);
    }

    public function destroy(TradeGood $tradeGood): JsonResponse
    {
        $tradeGood->delete();
        return response()->json(['success' => true, 'message' => '貿易商品已刪除']);
    }

    public function search(Request $request): JsonResponse
    {
        $q = $request->query('q', '');

        $results = TradeGood::when($q, fn($query) =>
            $query->where('name', 'like', "%{$q}%")
                  ->orWhere('product_code', 'like', "%{$q}%")
                  ->orWhere('hs_code', 'like', "%{$q}%")
        )
        ->select('id', 'name', 'product_code', 'hs_code')
        ->limit(20)
        ->get();

        return response()->json(['success' => true, 'data' => $results]);
    }

    public function cbamSummary(): JsonResponse
    {
        $summary = TradeGood::where('is_cbam_applicable', true)
            ->selectRaw('cbam_category, COUNT(*) as count, SUM(embedded_emissions * quantity) as total_embedded_emissions')
            ->groupBy('cbam_category')
            ->get();

        return response()->json(['success' => true, 'data' => $summary]);
    }

    // 上游供應商管理
    public function suppliers(TradeGood $tradeGood): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getUpstreamCompliance($tradeGood),
        ]);
    }

    public function addSupplier(Request $request, TradeGood $tradeGood): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id'       => ['required', 'uuid', 'exists:suppliers,id'],
            'material_group_id' => ['nullable', 'uuid', 'exists:material_groups,id'],
            'notes'             => ['nullable', 'string'],
        ]);

        $tgs = $tradeGood->tradeGoodSuppliers()->create($validated);

        return response()->json(['success' => true, 'data' => $tgs->load('supplier', 'materialGroup'), 'message' => '上游供應商已新增'], 201);
    }

    public function removeSupplier(TradeGood $tradeGood, TradeGoodSupplier $tradeGoodSupplier): JsonResponse
    {
        abort_if($tradeGoodSupplier->trade_good_id !== $tradeGood->id, 404);
        $tradeGoodSupplier->delete();
        return response()->json(['success' => true, 'message' => '上游供應商已移除']);
    }

    // 碳排回報管理（中心廠側）
    public function emissionReports(TradeGood $tradeGood): JsonResponse
    {
        $reports = $tradeGood->emissionReports()
            ->with('supplier:id,name,code')
            ->orderByDesc('reported_at')
            ->get();

        return response()->json(['success' => true, 'data' => $reports]);
    }

    public function confirmEmission(TradeGood $tradeGood, TradeGoodSupplierEmission $emission): JsonResponse
    {
        abort_if($emission->trade_good_id !== $tradeGood->id, 404);
        $this->service->confirmEmissions($emission);

        return response()->json(['success' => true, 'message' => '已確認採用此碳排數值', 'data' => ['embedded_emissions' => $emission->emissions_value]]);
    }
}
