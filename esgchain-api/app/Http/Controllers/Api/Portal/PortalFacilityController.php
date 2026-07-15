<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\ActivityDataReport;
use App\Models\SupplierFacility;
use App\Services\Supplier\ActivityDataReportService;
use App\Services\Supplier\SupplierFacilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalFacilityController extends Controller
{
    public function __construct(
        private readonly SupplierFacilityService   $facilityService,
        private readonly ActivityDataReportService $reportService
    ) {}

    public function index(): JsonResponse
    {
        $supplierId = auth()->user()->supplier_id;
        return response()->json(['data' => $this->facilityService->list($supplierId)]);
    }

    public function storeReport(Request $request, SupplierFacility $facility): JsonResponse
    {
        $supplierId = auth()->user()->supplier_id;
        abort_if($facility->supplier_id !== $supplierId, 403);

        $validated = $request->validate([
            'report_period'   => ['required', 'string', 'regex:/^\d{4}-Q[1-4]$/'],
            'electricity_kwh' => ['nullable', 'numeric', 'min:0'],
            'natural_gas_gj'  => ['nullable', 'numeric', 'min:0'],
            'fuel_oil_l'      => ['nullable', 'numeric', 'min:0'],
            'heat_gj'         => ['nullable', 'numeric', 'min:0'],
            'water_m3'        => ['nullable', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string'],
        ], [
            'report_period.regex' => '申報期間格式須為 YYYY-Q1~Q4，例：2024-Q1',
        ]);

        $report = $this->reportService->create($facility->id, $validated);
        return response()->json(['data' => $report], 201);
    }

    public function submitReport(SupplierFacility $facility, ActivityDataReport $report): JsonResponse
    {
        $supplierId = auth()->user()->supplier_id;
        abort_if($facility->supplier_id !== $supplierId, 403);
        abort_if($report->supplier_facility_id !== $facility->id, 403);

        $report = $this->reportService->submit($report);
        return response()->json(['data' => $report]);
    }
}
