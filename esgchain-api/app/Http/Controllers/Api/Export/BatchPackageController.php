<?php

namespace App\Http\Controllers\Api\Export;

use App\Http\Controllers\Controller;
use App\Models\BatchExportReview;
use App\Models\BomLineSupplier;
use App\Models\ProductBomLine;
use App\Models\ProductionBatch;
use App\Models\SalesProduct;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 批次護照（Batch Package）對外 API。
 * 供 DPP 平台 / 出口合規系統以批號拉取完整追溯與合規資料包。
 * 認證：X-Api-Key（VerifyExportApiKey middleware）。
 */
class BatchPackageController extends Controller
{
    public function show(Request $request, string $erpBatchNo): JsonResponse
    {
        $batch = ProductionBatch::with(['supplier:id,name,code,country_code,tier', 'rawMaterialOrigins'])
            ->where('erp_batch_no', $erpBatchNo)
            ->first();

        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Batch not found'], 404);
        }

        $product = SalesProduct::with('customer:id,name,code,country_code')->find($batch->sales_product_id);
        $market  = strtoupper((string) $request->query('market', ''));

        // 供應鏈：BOM 行 × 主供應商 × Tier
        $bomLines = ProductBomLine::with('materialGroup:id,name')
            ->where('sales_product_id', $batch->sales_product_id)->get();
        $primaries = BomLineSupplier::whereIn('bom_line_id', $bomLines->pluck('id'))
            ->where('role', 'primary')->get()->keyBy('bom_line_id');
        $suppliers = Supplier::whereIn('id', $primaries->pluck('supplier_id'))
            ->get(['id', 'name', 'code', 'country_code', 'tier'])->keyBy('id');

        $supplyChain = $bomLines->map(function ($line) use ($primaries, $suppliers) {
            $sup = $suppliers->get($primaries->get($line->id)?->supplier_id);
            return [
                'erp_line_id'    => $line->erp_line_id,
                'material'       => $line->material_name,
                'hs_code'        => $line->hs_code,
                'material_group' => $line->materialGroup?->name,
                'type'           => $line->bom_line_type,
                'quantity'       => (float) $line->quantity,
                'unit'           => $line->unit,
                'supplier'       => $sup ? [
                    'name' => $sup->name, 'code' => $sup->code,
                    'country' => $sup->country_code, 'tier' => $sup->tier,
                ] : null,
            ];
        })->values();

        // 審查結論（指定市場取單筆；未指定回全部市場）
        $reviewsQuery = BatchExportReview::where('production_batch_id', $batch->id);
        if ($market !== '') {
            $reviewsQuery->where('market', $market);
        }
        $reviews = $reviewsQuery->get()->map(fn($r) => [
            'market'      => $r->market,
            'status'      => $r->status,
            'findings'    => $r->findings,
            'reviewed_at' => $r->reviewed_at?->toISOString(),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $product ? [
                    'name'               => $product->name,
                    'sku'                => $product->product_code,
                    'model_no'           => $product->model_no,
                    'hs_code'            => $product->hs_code,
                    'description'        => $product->description,
                    'customer'           => $product->customer?->name,
                    'customer_country'   => $product->customer?->country_code,
                    'embedded_emissions' => $product->embedded_emissions !== null ? (float) $product->embedded_emissions : null,
                    'regulations'        => $product->applicable_regulations ?? [],
                ] : null,
                'batch' => [
                    'batch_no'        => $batch->erp_batch_no,
                    'order_no'        => $batch->erp_order_no,
                    'production_date' => $batch->production_date?->toDateString(),
                    'quantity'        => (float) $batch->quantity,
                    'unit'            => $batch->unit,
                    'lot_pcf'         => $batch->lot_pcf !== null ? (float) $batch->lot_pcf : null,
                    'lot_pcf_source'  => $batch->lot_pcf_source,
                    'factory'         => $batch->supplier ? [
                        'name' => $batch->supplier->name, 'code' => $batch->supplier->code,
                        'country' => $batch->supplier->country_code, 'tier' => $batch->supplier->tier,
                    ] : null,
                ],
                'supply_chain' => $supplyChain,
                'raw_material_origins' => $batch->rawMaterialOrigins->map(fn($o) => [
                    'material'      => $o->material_name,
                    'country'       => $o->origin_country,
                    'facility'      => $o->facility_name,
                    'gps'           => ($o->gps_lat && $o->gps_lng) ? "{$o->gps_lat}, {$o->gps_lng}" : null,
                    'harvest_year'  => $o->harvest_year,
                    'certification' => $o->certification_ref,
                ])->values(),
                'export_reviews' => $reviews,
                'meta' => [
                    'market_filter' => $market ?: null,
                    'generated_at'  => now()->toISOString(),
                    'source'        => 'ESG-Chain Batch Package API v1',
                ],
            ],
        ]);
    }
}
