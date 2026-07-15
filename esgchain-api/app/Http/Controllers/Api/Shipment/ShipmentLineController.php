<?php

namespace App\Http\Controllers\Api\Shipment;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentLine;
use App\Services\Shipment\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentLineController extends Controller
{
    public function __construct(private ShipmentService $service) {}

    public function store(Request $request, string $shipmentId): JsonResponse
    {
        $shipment = Shipment::findOrFail($shipmentId);

        $data = $request->validate([
            'sales_product_id' => ['required', 'uuid', 'exists:sales_products,id'],
            'total_quantity'   => ['required', 'numeric', 'min:0'],
            'unit'             => ['nullable', 'string', 'max:20'],
            'hs_code_override' => ['nullable', 'string', 'max:10'],
        ]);

        $line = $this->service->addLine($shipment, $data);
        $line->load('salesProduct:id,name,product_code,hs_code,is_eudr_applicable');

        return response()->json(['success' => true, 'data' => $line], 201);
    }

    public function destroy(string $shipmentId, string $lineId): JsonResponse
    {
        ShipmentLine::where('shipment_id', $shipmentId)->findOrFail($lineId)->delete();
        return response()->json(['success' => true]);
    }
}
