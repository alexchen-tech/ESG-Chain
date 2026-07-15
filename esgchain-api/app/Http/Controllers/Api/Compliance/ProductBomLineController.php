<?php

namespace App\Http\Controllers\Api\Compliance;

use App\Http\Controllers\Controller;
use App\Models\MaterialItem;
use App\Models\PcfRequestLine;
use App\Models\ProductBomLine;
use App\Models\SalesProduct;
use App\Services\Compliance\BomLineImportService;
use App\Services\Compliance\ProductBomLineService;
use App\Services\PCF\PcfEmissionGapScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductBomLineController extends Controller
{
    public function __construct(
        private BomLineImportService $importService,
        private ProductBomLineService $bomLineService,
        private PcfEmissionGapScanService $gapScanService,
    ) {}

    public function index(SalesProduct $salesProduct): JsonResponse
    {
        $lines = $salesProduct->bomLines()
            ->with(['materialGroup', 'materialItem.materialGroup', 'bomLineSuppliers.supplier', 'childSalesProduct'])
            ->get()
            ->map(function ($line) {
                $item = $line->materialItem;
                $line->effective_material_name  = $item ? $item->name         : $line->material_name;
                $line->effective_hs_code        = $item ? $item->hs_code      : $line->hs_code;
                $line->effective_material_group = $item ? $item->materialGroup : $line->materialGroup;

                // BOM 原物料直接對應供應商（主供應商＋Tier），來源為 ERP AVL
                $primary = $line->bomLineSuppliers->firstWhere('role', 'primary') ?? $line->bomLineSuppliers->first();
                $line->primary_supplier = $primary?->supplier ? [
                    'id'   => $primary->supplier->id,
                    'name' => $primary->supplier->name,
                    'code' => $primary->supplier->code,
                    'tier' => $primary->supplier->tier,
                ] : null;
                return $line;
            });

        return response()->json([
            'success' => true,
            'data'    => $lines,
        ]);
    }

    public function store(Request $request, SalesProduct $salesProduct): JsonResponse
    {
        $validated = $request->validate([
            'erp_line_id'            => ['nullable', 'string', 'max:100'],
            'material_name'          => ['required_without:child_sales_product_id', 'nullable', 'string', 'max:255'],
            'hs_code'                => ['nullable', 'string', 'max:10'],
            'material_item_id'       => ['nullable', 'uuid', 'exists:material_items,id'],
            'material_group_id'      => ['nullable', 'uuid', 'exists:material_groups,id'],
            'bom_line_type'          => ['nullable', 'in:material,service'],
            'notes'                  => ['nullable', 'string'],
            'child_sales_product_id' => ['nullable', 'uuid', 'exists:sales_products,id'],
        ]);

        $validated['sales_product_id']    = $salesProduct->id;
        $validated['material_group_source'] = 'manual';
        $validated['material_name']       = $validated['material_name'] ?? '';

        if (!empty($validated['material_item_id'])) {
            $materialItem = MaterialItem::find($validated['material_item_id']);
            if ($materialItem) {
                $validated['material_name'] = $materialItem->name;
                $validated['hs_code']       = $materialItem->hs_code;
                if (!array_key_exists('material_group_id', $validated) && $materialItem->material_group_id) {
                    $validated['material_group_id']     = $materialItem->material_group_id;
                    $validated['material_group_source'] = 'erp_imported';
                }
            }
            $validated['linkage_status'] = 'linked';
        } else {
            $validated['linkage_status'] = 'unlinked';
        }

        $line = $this->bomLineService->create($salesProduct, $validated);

        return response()->json([
            'success' => true,
            'data'    => $line->load(['materialGroup', 'bomLineSuppliers.supplier', 'childSalesProduct']),
            'message' => 'BOM 明細已建立',
        ], 201);
    }

    public function update(Request $request, SalesProduct $salesProduct, ProductBomLine $bomLine): JsonResponse
    {
        $validated = $request->validate([
            'erp_line_id'            => ['sometimes', 'nullable', 'string', 'max:100'],
            'material_name'          => ['sometimes', 'string', 'max:255'],
            'hs_code'                => ['sometimes', 'nullable', 'string', 'max:10'],
            'material_item_id'       => ['sometimes', 'nullable', 'uuid', 'exists:material_items,id'],
            'material_group_id'      => ['sometimes', 'nullable', 'uuid', 'exists:material_groups,id'],
            'bom_line_type'          => ['sometimes', 'in:material,service'],
            'notes'                  => ['sometimes', 'nullable', 'string'],
            'child_sales_product_id' => ['sometimes', 'nullable', 'uuid', 'exists:sales_products,id'],
        ]);

        if (!empty($validated['child_sales_product_id'])) {
            $this->bomLineService->assertNoCycle($salesProduct->id, $validated['child_sales_product_id']);
        }

        if (array_key_exists('material_group_id', $validated)) {
            $validated['material_group_source'] = 'manual';
        }

        if (array_key_exists('material_item_id', $validated)) {
            if (!empty($validated['material_item_id'])) {
                $materialItem = MaterialItem::find($validated['material_item_id']);
                if ($materialItem) {
                    $validated['material_name'] = $materialItem->name;
                    $validated['hs_code']       = $materialItem->hs_code;
                    if (!array_key_exists('material_group_id', $validated) && $materialItem->material_group_id) {
                        $validated['material_group_id']     = $materialItem->material_group_id;
                        $validated['material_group_source'] = 'erp_imported';
                    }
                }
                $validated['linkage_status'] = 'linked';
            } else {
                $validated['linkage_status'] = 'unlinked';
            }
        }

        $bomLine->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $bomLine->fresh(['materialGroup', 'bomLineSuppliers.supplier', 'childSalesProduct']),
            'message' => 'BOM 明細已更新',
        ]);
    }

    public function destroy(SalesProduct $salesProduct, ProductBomLine $bomLine): JsonResponse
    {
        $bomLine->delete();

        return response()->json([
            'success' => true,
            'data'    => null,
            'message' => 'BOM 明細已刪除',
        ]);
    }

    public function import(Request $request, SalesProduct $salesProduct): JsonResponse
    {
        $contentType = $request->header('Content-Type', '');

        if (str_contains($contentType, 'multipart/form-data') || $request->hasFile('file')) {
            $request->validate([
                'file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
            ]);

            $result = $this->importService->importFromCsv($salesProduct, $request->file('file'));
        } else {
            $rows = $request->validate([
                '*'               => ['array'],
                '*.erp_line_id'   => ['required', 'string'],
                '*.material_name' => ['required', 'string'],
                '*.hs_code'       => ['nullable', 'string'],
                '*.quantity'      => ['nullable', 'numeric'],
                '*.unit'          => ['nullable', 'string'],
                '*.unit_price'    => ['nullable', 'numeric'],
                '*.currency'      => ['nullable', 'string', 'size:3'],
                '*.supplier_code' => ['nullable', 'string'],
            ]);

            $result = $this->importService->importFromArray($salesProduct, $rows);
        }

        return response()->json([
            'success' => true,
            'data'    => $result,
            'message' => "匯入完成：新增 {$result['created']} 筆，更新 {$result['updated']} 筆",
        ]);
    }

    public function requestEmission(SalesProduct $salesProduct, ProductBomLine $bomLine): JsonResponse
    {
        $primarySupplier = $bomLine->bomLineSuppliers()->where('role', 'primary')->first();

        if (!$primarySupplier) {
            return response()->json([
                'success' => false,
                'message' => '此 BOM 明細尚未指定主要供應商，無法建立碳排填報請求',
            ], 422);
        }

        if (!$bomLine->material_item_id) {
            return response()->json([
                'success' => false,
                'message' => '此 BOM 明細尚未關聯物料，無法建立碳排填報請求',
            ], 422);
        }

        $hasPending = PcfRequestLine::whereHas(
            'pcfRequest',
            fn ($q) => $q->where('supplier_id', $primarySupplier->supplier_id)
        )
            ->where('material_item_id', $bomLine->material_item_id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return response()->json([
                'success' => false,
                'message' => '此物料已有待填報的碳排請求，請勿重複建立',
            ], 409);
        }

        $result = $this->gapScanService->scan(
            salesProductId: $salesProduct->id,
            supplierId: $primarySupplier->supplier_id,
            triggerSource: 'buyer_manual',
        );

        return response()->json([
            'success' => true,
            'data'    => $result,
            'message' => "碳排填報請求已建立（新增 {$result['created']} 筆）",
        ], 201);
    }
}
