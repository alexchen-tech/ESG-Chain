<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Http\Controllers\Controller;
use App\Models\ActivityDataReport;
use App\Models\Supplier;
use App\Services\Supplier\ActivityDataReportService;
use Illuminate\Http\JsonResponse;

class ActivityDataReportController extends Controller
{
    public function __construct(
        private readonly ActivityDataReportService $service
    ) {}

    public function index(Supplier $supplier): JsonResponse
    {
        $reports = ActivityDataReport::whereHas('facility', fn($q) => $q->where('supplier_id', $supplier->id))
            ->with('facility:id,name')
            ->latest()
            ->get();

        return response()->json(['data' => $reports]);
    }

    public function verify(Supplier $supplier, ActivityDataReport $report): JsonResponse
    {
        $report = $this->service->verify($report);
        return response()->json(['data' => $report]);
    }

    public function push(Supplier $supplier, ActivityDataReport $report): JsonResponse
    {
        $this->service->retryPush($report);
        return response()->json(['success' => true, 'message' => '推送任務已重新排程']);
    }
}
