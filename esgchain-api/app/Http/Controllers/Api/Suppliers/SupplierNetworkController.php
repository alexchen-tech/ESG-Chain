<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Http\Controllers\Controller;
use App\Models\BomLineSupplier;
use App\Models\SalesProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierNetworkController extends Controller
{
    /**
     * GET /api/v1/suppliers/network-graph?sales_product_id=xxx
     * 供應鏈連結圖：以單一銷售產品為出發點，依 Supplier.tier（1~4）分欄呈現該產品 BOM
     * 涉及的供應商。tier 目前是手動設定的扁平屬性、非逐層追溯資料，因此同一欄內的供應商
     * 彼此沒有連線關係，僅表示「都供應這個產品」且各自標記的供應商層級。
     */
    public function graph(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sales_product_id' => ['required', 'uuid', 'exists:sales_products,id'],
        ]);

        $product = SalesProduct::select('id', 'name', 'product_code')->findOrFail($validated['sales_product_id']);

        $links = BomLineSupplier::with([
            'supplier:id,name,code,tier,country_code',
            'bomLine:id,sales_product_id,material_item_id,material_name',
            'bomLine.materialItem:id,name',
        ])
            ->whereHas('bomLine', fn ($q) => $q->where('sales_product_id', $product->id))
            ->get();

        $nodes = [
            "product:{$product->id}" => [
                'id'     => "product:{$product->id}",
                'type'   => 'product',
                'label'  => $product->name,
                'ref_id' => $product->id,
                'code'   => $product->product_code,
            ],
        ];
        $edgeKeys = [];
        $edges = [];

        foreach ($links as $link) {
            $bomLine = $link->bomLine;
            $supplier = $link->supplier;
            if (!$bomLine || !$supplier) continue;

            $materialKey = $bomLine->material_item_id
                ? "material:item:{$bomLine->material_item_id}"
                : 'material:name:' . md5($bomLine->material_name ?? 'unknown');

            if (!isset($nodes[$materialKey])) {
                $nodes[$materialKey] = [
                    'id'     => $materialKey,
                    'type'   => 'material',
                    'label'  => $bomLine->materialItem?->name ?? $bomLine->material_name ?? '未命名物料',
                    'ref_id' => $bomLine->material_item_id,
                ];
            }

            $supplierKey = "supplier:{$supplier->id}";
            if (!isset($nodes[$supplierKey])) {
                $nodes[$supplierKey] = [
                    'id'      => $supplierKey,
                    'type'    => 'supplier',
                    'label'   => $supplier->name,
                    'ref_id'  => $supplier->id,
                    'code'    => $supplier->code,
                    'tier'    => $supplier->tier,
                    'country' => $supplier->country_code,
                ];
            }

            $materialProductKey = "{$materialKey}->product:{$product->id}";
            if (!isset($edgeKeys[$materialProductKey])) {
                $edgeKeys[$materialProductKey] = true;
                $edges[] = ['source' => $materialKey, 'target' => "product:{$product->id}"];
            }

            $supplierMaterialKey = "{$supplierKey}->{$materialKey}";
            if (!isset($edgeKeys[$supplierMaterialKey])) {
                $edgeKeys[$supplierMaterialKey] = true;
                $edges[] = ['source' => $supplierKey, 'target' => $materialKey];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'nodes' => array_values($nodes),
                'edges' => $edges,
            ],
        ]);
    }
}
