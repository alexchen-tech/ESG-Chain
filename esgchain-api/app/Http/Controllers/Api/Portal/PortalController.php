<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\Compliance\SupplierComplianceStatusService;
use Illuminate\Http\JsonResponse;

class PortalController extends Controller
{
    public function __construct(
        private readonly SupplierComplianceStatusService $service,
    ) {}

    public function procurementRequirements(): JsonResponse
    {
        $supplierId = auth()->user()->supplier_id;

        if (!$supplierId) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $supplier = Supplier::find($supplierId);

        if (!$supplier) {
            return response()->json(['success' => true, 'data' => []]);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->service->getSupplierBomRequirements($supplier),
        ]);
    }
}
