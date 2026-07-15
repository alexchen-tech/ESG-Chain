<?php

namespace App\Http\Controllers\Api\Shipment;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Shipment\ShipmentService;
use Illuminate\Http\JsonResponse;

class ShipmentDdsController extends Controller
{
    public function __construct(private ShipmentService $service) {}

    public function draft(string $shipmentId): JsonResponse
    {
        $shipment = Shipment::findOrFail($shipmentId);

        if ($shipment->eudr_dds_status === 'not_required') {
            return response()->json(['message' => '此申報批次不適用 EUDR DDS'], 404);
        }

        $draft = $this->service->generateDdsDraft($shipment);

        return response()->json(['success' => true, 'data' => $draft]);
    }
}
