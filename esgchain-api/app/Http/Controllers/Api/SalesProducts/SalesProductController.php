<?php

namespace App\Http\Controllers\Api\SalesProducts;

use App\Http\Controllers\Controller;
use App\Models\SalesProduct;
use App\Models\TradeGoodSupplier;
use App\Models\TradeGoodSupplierEmission;
use App\Services\TradeGoods\TradeGoodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesProductController extends Controller
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
            'name'                    => ['required', 'string', 'max:255'],
            'product_code'            => ['nullable', 'string', 'max:100'],
            'model_no'                => ['nullable', 'string', 'max:100'],
            'hs_code'                 => ['required', 'string', 'max:10'],
            'description'             => ['nullable', 'string'],
            'unit'                    => ['nullable', 'string', 'max:20'],
            'quantity'                => ['nullable', 'numeric', 'min:0'],
            'unit_price'              => ['nullable', 'numeric', 'min:0'],
            'currency'                => ['nullable', 'string', 'size:3'],
            'material_group_id'       => ['nullable', 'uuid', 'exists:material_groups,id'],
            'customer_id'             => ['nullable', 'uuid', 'exists:customers,id'],
            'applicable_regulations'  => ['nullable', 'array'],
            'applicable_regulations.*' => ['string'],
        ]);

        $cbam = SalesProduct::checkCbamApplicability($validated['hs_code']);
        $validated['is_cbam_applicable'] = $cbam['is_applicable'];
        $validated['cbam_category']      = $cbam['category'];
        $validated['is_eudr_applicable'] = SalesProduct::checkEudrApplicability($validated['hs_code']);
        $validated['dpp_category']       = SalesProduct::checkDppCategory($validated['hs_code']);

        $product = SalesProduct::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $this->service->summarize($product->load('tradeGoodSuppliers.materialGroup', 'tradeGoodSuppliers.supplier.complianceDocs')),
            'message' => '銷售產品已建立' . ($cbam['is_applicable'] ? "（CBAM：{$cbam['category']}）" : ''),
        ], 201);
    }

    public function show(SalesProduct $salesProduct): JsonResponse
    {
        $salesProduct->load(
            'tradeGoodSuppliers.materialGroup',
            'tradeGoodSuppliers.supplier.complianceDocs',
            'materialGroup',
            'productionBatches.supplier:id,name,code',
        );
        return response()->json([
            'success' => true,
            'data'    => array_merge(
                $this->service->summarize($salesProduct),
                [
                    'applicable_regulations' => $salesProduct->applicable_regulations,
                    'inferred_regulations'   => $salesProduct->inferred_regulations,
                    'upstream_details'       => $this->service->getUpstreamCompliance($salesProduct),
                    'production_batches'     => $salesProduct->productionBatches->map(fn($b) => [
                        'id'              => $b->id,
                        'erp_batch_no'    => $b->erp_batch_no,
                        'erp_order_no'    => $b->erp_order_no,
                        'production_date' => $b->production_date?->toDateString(),
                        'quantity'        => $b->quantity,
                        'unit'            => $b->unit,
                        'lot_pcf'         => $b->lot_pcf,
                        'lot_pcf_source'  => $b->lot_pcf_source,
                        'supplier_name'   => $b->supplier?->name,
                        'supplier_code'   => $b->supplier?->code,
                        'source'          => $b->source,
                    ])->values(),
                ]
            ),
        ]);
    }

    public function update(Request $request, SalesProduct $salesProduct): JsonResponse
    {
        if ($request->filled('product_code') && $request->input('product_code') !== $salesProduct->product_code) {
            return response()->json([
                'success' => false,
                'message' => '產品編碼（product_code）僅可透過 ERP 同步建立，不可修改',
            ], 422);
        }

        $validated = $request->validate([
            'name'                    => ['sometimes', 'string', 'max:255'],
            'model_no'                => ['sometimes', 'nullable', 'string', 'max:100'],
            'hs_code'                 => ['sometimes', 'string', 'max:10'],
            'description'             => ['sometimes', 'nullable', 'string'],
            'unit'                    => ['sometimes', 'nullable', 'string'],
            'quantity'                => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'unit_price'              => ['sometimes', 'nullable', 'numeric'],
            'currency'                => ['sometimes', 'string', 'size:3'],
            'material_group_id'       => ['sometimes', 'nullable', 'uuid', 'exists:material_groups,id'],
            'customer_id'             => ['sometimes', 'nullable', 'uuid', 'exists:customers,id'],
            'applicable_regulations'  => ['sometimes', 'nullable', 'array'],
            'applicable_regulations.*' => ['string'],
            'dpp_category'            => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        // dpp_category 若在此次請求中被明確指定，視為人工覆寫，優先於 HS Code 自動判定
        $dppCategoryOverridden = array_key_exists('dpp_category', $validated);

        if (isset($validated['hs_code'])) {
            $cbam = SalesProduct::checkCbamApplicability($validated['hs_code']);
            $validated['is_cbam_applicable'] = $cbam['is_applicable'];
            $validated['cbam_category']      = $cbam['category'];
            $validated['is_eudr_applicable'] = SalesProduct::checkEudrApplicability($validated['hs_code']);
            if (!$dppCategoryOverridden) {
                $validated['dpp_category'] = SalesProduct::checkDppCategory($validated['hs_code']);
            }
        }

        $salesProduct->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $this->service->summarize($salesProduct->fresh()->load('tradeGoodSuppliers.materialGroup', 'tradeGoodSuppliers.supplier.complianceDocs')),
            'message' => '銷售產品已更新',
        ]);
    }

    public function destroy(SalesProduct $salesProduct): JsonResponse
    {
        $salesProduct->delete();
        return response()->json(['success' => true, 'message' => '銷售產品已刪除']);
    }

    public function search(Request $request): JsonResponse
    {
        $q = $request->query('q', '');

        $results = SalesProduct::when($q, fn($query) =>
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
        $summary = SalesProduct::where('is_cbam_applicable', true)
            ->selectRaw('cbam_category, COUNT(*) as count, SUM(embedded_emissions * quantity) as total_embedded_emissions')
            ->groupBy('cbam_category')
            ->get();

        return response()->json(['success' => true, 'data' => $summary]);
    }

    public function suppliers(SalesProduct $salesProduct): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getUpstreamCompliance($salesProduct),
        ]);
    }

    public function addSupplier(Request $request, SalesProduct $salesProduct): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id'          => ['required', 'uuid', 'exists:suppliers,id'],
            'supplier_facility_id' => ['nullable', 'uuid', 'exists:supplier_facilities,id'],
            'material_group_id'    => ['nullable', 'uuid', 'exists:material_groups,id'],
            'notes'                => ['nullable', 'string'],
        ]);

        $tgs = $salesProduct->tradeGoodSuppliers()->create($validated);

        return response()->json(['success' => true, 'data' => $tgs->load('supplier', 'supplierFacility', 'materialGroup'), 'message' => '上游供應商已新增'], 201);
    }

    public function removeSupplier(SalesProduct $salesProduct, TradeGoodSupplier $tradeGoodSupplier): JsonResponse
    {
        abort_if($tradeGoodSupplier->trade_good_id !== $salesProduct->id, 404);
        $tradeGoodSupplier->delete();
        return response()->json(['success' => true, 'message' => '上游供應商已移除']);
    }

    public function emissionReports(SalesProduct $salesProduct): JsonResponse
    {
        $reports = $salesProduct->emissionReports()
            ->with('supplier:id,name,code')
            ->orderByDesc('reported_at')
            ->get();

        return response()->json(['success' => true, 'data' => $reports]);
    }

    public function confirmEmission(SalesProduct $salesProduct, TradeGoodSupplierEmission $emission): JsonResponse
    {
        abort_if($emission->trade_good_id !== $salesProduct->id, 404);
        $this->service->confirmEmissions($emission);

        return response()->json(['success' => true, 'message' => '已確認採用此碳排數值', 'data' => ['embedded_emissions' => $emission->emissions_value]]);
    }
}
