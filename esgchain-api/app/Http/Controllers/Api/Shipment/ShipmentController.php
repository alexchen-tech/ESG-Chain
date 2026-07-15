<?php

namespace App\Http\Controllers\Api\Shipment;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Shipment\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function __construct(private ShipmentService $service) {}

    public function index(): JsonResponse
    {
        $shipments = Shipment::with(['lines.salesProduct:id,name,product_code,is_eudr_applicable'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($s) => $this->summarize($s));

        return response()->json(['success' => true, 'data' => $shipments]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipment_no'     => ['nullable', 'string', 'max:100', 'unique:shipments,shipment_no'],
            'customer_id'     => ['nullable', 'uuid', 'exists:customers,id'],
            'customer_po_no'  => ['nullable', 'string', 'max:100'],
            'target_market'   => ['nullable', 'string', 'max:10'],
            'export_date'     => ['nullable', 'date'],
            'eudr_dds_status' => ['nullable', 'in:not_required,draft,submitted,approved'],
            'notes'           => ['nullable', 'string'],
        ]);

        ['shipment' => $shipment, 'warnings' => $warnings] = $this->service->create($data, $request->user());

        return response()->json([
            'success'  => true,
            'data'     => $this->summarize($shipment),
            'warnings' => $warnings,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $shipment = Shipment::with([
            'customer:id,name,code,country_code,eori_number,customer_type',
            'lines.salesProduct:id,name,product_code,hs_code,is_eudr_applicable',
            'lines.lineBatches.productionBatch.supplier:id,name,code',
            'lines.lineBatches.productionBatch.rawMaterialOrigins',
            'creator:id,name,email',
        ])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $this->detail($shipment)]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $shipment = Shipment::findOrFail($id);

        $data = $request->validate([
            'customer_id'      => ['nullable', 'uuid', 'exists:customers,id'],
            'customer_po_no'   => ['nullable', 'string', 'max:100'],
            'target_market'    => ['sometimes', 'string', 'max:10'],
            'export_date'      => ['nullable', 'date'],
            'eudr_dds_status'  => ['sometimes', 'in:not_required,draft,submitted,approved'],
            'eudr_dds_ref'     => ['nullable', 'string', 'max:200'],
            'eudr_submitted_at'=> ['nullable', 'date'],
            'notes'            => ['nullable', 'string'],
        ]);

        if (!empty($data['eudr_dds_ref']) && $shipment->eudr_dds_status !== 'approved') {
            $data['eudr_dds_status']   = 'submitted';
            $data['eudr_submitted_at'] = now();
        }

        $shipment->update($data);

        return response()->json(['success' => true, 'data' => $this->summarize($shipment)]);
    }

    public function destroy(string $id): JsonResponse
    {
        Shipment::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    private function summarize(Shipment $s): array
    {
        return [
            'id'              => $s->id,
            'shipment_no'     => $s->shipment_no,
            'customer_id'     => $s->customer_id,
            'customer_name'   => $s->customer?->name,
            'customer_po_no'  => $s->customer_po_no,
            'target_market'   => $s->target_market,
            'export_date'     => $s->export_date?->toDateString(),
            'eudr_dds_status' => $s->eudr_dds_status,
            'eudr_dds_ref'    => $s->eudr_dds_ref,
            'line_count'      => $s->lines?->count() ?? 0,
            'notes'           => $s->notes,
            'created_at'      => $s->created_at->toISOString(),
        ];
    }

    private function detail(Shipment $s): array
    {
        return array_merge($this->summarize($s), [
            'eudr_submitted_at' => $s->eudr_submitted_at?->toISOString(),
            'creator'           => $s->creator ? ['id' => $s->creator->id, 'name' => $s->creator->name] : null,
            'lines'             => $s->lines->map(fn($l) => [
                'id'             => $l->id,
                'trade_good_id'  => $l->sales_product_id,
                'trade_good_name'=> $l->salesProduct?->name,
                'trade_good_code'=> $l->salesProduct?->product_code,
                'hs_code'        => $l->hs_code_override ?? $l->salesProduct?->hs_code,
                'is_eudr'        => $l->salesProduct?->is_eudr_applicable ?? false,
                'total_quantity'    => $l->total_quantity,
                'unit'              => $l->unit,
                'weighted_pcf'      => $l->weighted_pcf,
                'line_batches'      => $l->lineBatches->map(fn($lb) => [
                    'id'                 => $lb->id,
                    'production_batch_id'=> $lb->production_batch_id,
                    'erp_batch_no'       => $lb->productionBatch?->erp_batch_no,
                    'supplier_name'      => $lb->productionBatch?->supplier?->name,
                    'lot_pcf'            => $lb->productionBatch?->lot_pcf,
                    'allocated_quantity' => $lb->allocated_quantity,
                    'origins_count'      => $lb->productionBatch?->rawMaterialOrigins?->count() ?? 0,
                ])->values(),
            ])->values(),
        ]);
    }
}
