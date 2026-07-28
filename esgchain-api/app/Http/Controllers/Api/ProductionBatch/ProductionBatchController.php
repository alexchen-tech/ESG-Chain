<?php

namespace App\Http\Controllers\Api\ProductionBatch;

use App\Http\Controllers\Controller;
use App\Models\ProductionBatch;
use App\Services\ProductionBatch\ProductionBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionBatchController extends Controller
{
    public function __construct(private ProductionBatchService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['matched_status', 'supplier_id']);
        $batches = $this->service->list($filters);

        return response()->json(['success' => true, 'data' => $batches->map(fn($b) => $this->format($b))]);
    }

    public function show(string $id): JsonResponse
    {
        $batch = ProductionBatch::with([
            'supplier:id,name,code',
            'salesProduct:id,name,product_code,model_no',
            'rawMaterialOrigins',
        ])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $this->format($batch)]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $batch = ProductionBatch::findOrFail($id);

        $data = $request->validate([
            'sales_product_id' => ['nullable', 'uuid', 'exists:sales_products,id'],
            'production_date'  => ['nullable', 'date'],
            'quantity'         => ['nullable', 'numeric', 'min:0'],
            'unit'             => ['nullable', 'string', 'max:20'],
            'lot_pcf'          => ['nullable', 'numeric', 'min:0'],
            'lot_pcf_source'   => ['nullable', 'in:calculated,reported,estimated'],
        ]);

        $batch->update($data);
        $batch->load(['supplier:id,name,code', 'salesProduct:id,name,product_code,model_no', 'rawMaterialOrigins']);

        return response()->json(['success' => true, 'data' => $this->format($batch)]);
    }

    public function destroy(string $id): JsonResponse
    {
        ProductionBatch::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    private function format(ProductionBatch $b): array
    {
        return [
            'id'                          => $b->id,
            'erp_batch_no'                => $b->erp_batch_no,
            'erp_order_no'                => $b->erp_order_no,
            'matched'                     => $b->sales_product_id !== null,
            'sales_product_id'            => $b->sales_product_id,
            'sales_product_name'          => $b->salesProduct?->name,
            'sales_product_code'          => $b->salesProduct?->product_code,
            'sales_product_model_no'      => $b->salesProduct?->model_no,
            'supplier_id'                 => $b->supplier_id,
            'supplier_name'               => $b->supplier?->name,
            'supplier_code'               => $b->supplier?->code,
            'production_date'             => $b->production_date?->toDateString(),
            'quantity'                    => $b->quantity,
            'unit'                        => $b->unit,
            'lot_pcf'                     => $b->lot_pcf,
            'lot_pcf_source'              => $b->lot_pcf_source,
            'source'                      => $b->source,
            'erp_synced_at'               => $b->erp_synced_at?->toISOString(),
            'raw_material_origins'        => $b->rawMaterialOrigins->map(fn($o) => [
                'id'               => $o->id,
                'material_name'    => $o->material_name,
                'origin_country'   => $o->origin_country,
                'facility_name'    => $o->facility_name,
                'gps_lat'          => $o->gps_lat,
                'gps_lng'          => $o->gps_lng,
                'harvest_year'     => $o->harvest_year,
                'certification_ref'=> $o->certification_ref,
                'bom_line_id'      => $o->bom_line_id,
            ])->values(),
            'created_at'                  => $b->created_at->toISOString(),
        ];
    }
}
