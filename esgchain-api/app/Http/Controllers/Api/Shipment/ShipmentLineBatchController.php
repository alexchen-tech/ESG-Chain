<?php

namespace App\Http\Controllers\Api\Shipment;

use App\Http\Controllers\Controller;
use App\Models\ProductionBatch;
use App\Models\ShipmentLine;
use App\Models\ShipmentLineBatch;
use App\Services\Shipment\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentLineBatchController extends Controller
{
    public function __construct(private ShipmentService $service) {}

    public function store(Request $request, string $shipmentId, string $lineId): JsonResponse
    {
        $line  = ShipmentLine::where('shipment_id', $shipmentId)->findOrFail($lineId);
        $data  = $request->validate([
            'production_batch_id' => ['required', 'uuid', 'exists:production_batches,id'],
            'allocated_quantity'  => ['required', 'numeric', 'min:0.0001'],
        ]);

        $batch  = ProductionBatch::findOrFail($data['production_batch_id']);
        $result = $this->service->allocateBatch($line, $batch, (float) $data['allocated_quantity']);

        return response()->json([
            'success'  => true,
            'data'     => $result['lineBatch'],
            'warnings' => $result['warnings'],
        ], 201);
    }

    public function destroy(string $shipmentId, string $lineId, string $batchId): JsonResponse
    {
        $lb = ShipmentLineBatch::where('shipment_line_id', $lineId)->findOrFail($batchId);
        $lb->delete();

        $line = ShipmentLine::find($lineId);
        if ($line) $this->service->recalcWeightedPcf($line);

        return response()->json(['success' => true]);
    }
}
