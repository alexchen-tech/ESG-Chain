<?php

namespace App\Http\Controllers\Api\Compliance;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchMaterialEmissionEstimate;
use App\Jobs\PcfEmissionGapScanJob;
use App\Models\BomLineSupplier;
use App\Models\MaterialItemEmission;
use App\Models\ProductBomLine;
use App\Models\SalesProduct;
use App\Services\PCF\PcfCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BomLineSupplierController extends Controller
{
    public function __construct(
        private readonly PcfCalculationService $pcfService
    ) {}

    public function setRole(Request $request, SalesProduct $salesProduct, ProductBomLine $bomLine, BomLineSupplier $bomLineSupplier): JsonResponse
    {
        abort_if($bomLineSupplier->bom_line_id !== $bomLine->id, 404);

        $validated = $request->validate([
            'role' => 'required|in:primary,alternate',
        ]);

        DB::transaction(function () use ($bomLine, $bomLineSupplier, $validated) {
            if ($validated['role'] === 'primary') {
                // 將現有 primary 降為 alternate
                $bomLine->bomLineSuppliers()
                    ->where('role', 'primary')
                    ->where('id', '!=', $bomLineSupplier->id)
                    ->update(['role' => 'alternate']);
            }
            $bomLineSupplier->update(['role' => $validated['role']]);
        });

        // 角色升為 primary：觸發缺口掃描（4.1）
        if ($validated['role'] === 'primary') {
            PcfEmissionGapScanJob::dispatch($salesProduct->id, $bomLineSupplier->supplier_id, 'system_supplier_change');
        }

        // 非同步觸發 PCF 重算（同步呼叫）
        dispatch(function () use ($salesProduct) {
            $this->pcfService->snapshot($salesProduct);
        })->afterResponse();

        return response()->json([
            'success' => true,
            'data'    => $bomLineSupplier->fresh()->load('supplier'),
        ]);
    }

    public function store(Request $request, SalesProduct $salesProduct, ProductBomLine $bomLine): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:suppliers,id'],
            'role'        => ['required', 'in:primary,alternate'],
        ]);

        // 手動指派時，供應商必須為已認證狀態
        $isManual = ! $request->has('source') || $request->input('source') === 'manual';
        if ($isManual) {
            $supplier = \App\Models\Supplier::find($validated['supplier_id']);
            if ($supplier && $supplier->onboarding_stage !== 'certified') {
                return response()->json([
                    'success' => false,
                    'message' => "此供應商尚未通過認證（onboarding_stage: {$supplier->onboarding_stage}），無法指派至 BOM 明細",
                ], 422);
            }
        }

        $alreadyLinked = $bomLine->bomLineSuppliers()
            ->where('supplier_id', $validated['supplier_id'])
            ->exists();
        if ($alreadyLinked) {
            return response()->json([
                'success' => false,
                'message' => '此供應商已在此 BOM 明細的供應商清單中',
            ], 422);
        }

        if ($validated['role'] === 'primary') {
            $alreadyHasPrimary = $bomLine->bomLineSuppliers()->where('role', 'primary')->exists();
            if ($alreadyHasPrimary) {
                return response()->json([
                    'success' => false,
                    'message' => '每條 BOM 明細只能有一個主要供應商',
                ], 422);
            }
        }

        $link = $bomLine->bomLineSuppliers()->create([
            'supplier_id' => $validated['supplier_id'],
            'role'        => $validated['role'],
            'source'      => 'manual',
            'sort_order'  => $bomLine->bomLineSuppliers()->max('sort_order') + 1,
        ]);

        // 4.1: 新增 primary supplier 觸發缺口掃描
        if ($validated['role'] === 'primary') {
            PcfEmissionGapScanJob::dispatch($salesProduct->id, $validated['supplier_id'], 'system_supplier_change');
        }

        // 若為 primary 且無碳排記錄，觸發 AI 估算
        if ($validated['role'] === 'primary' && $bomLine->material_item_id) {
            $hasEmission = MaterialItemEmission::where('material_item_id', $bomLine->material_item_id)
                ->where('supplier_id', $validated['supplier_id'])
                ->exists();

            if (! $hasEmission) {
                DispatchMaterialEmissionEstimate::dispatch(
                    $bomLine->material_item_id,
                    $validated['supplier_id'],
                    $bomLine->materialItem?->hs_code ?? $bomLine->hs_code ?? '',
                    $bomLine->materialItem?->name ?? $bomLine->material_name,
                );
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $link->load('supplier'),
        ], 201);
    }

    public function destroy(SalesProduct $salesProduct, ProductBomLine $bomLine, BomLineSupplier $bomLineSupplier): JsonResponse
    {
        abort_if($bomLineSupplier->bom_line_id !== $bomLine->id, 404);

        $bomLineSupplier->delete();

        return response()->json(['success' => true, 'message' => '供應商關聯已移除']);
    }
}
