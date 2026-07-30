<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Http\Controllers\Controller;
use App\Models\ActivityDataReport;
use App\Services\Supplier\ActivityDataReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 中心廠端跨供應商 Scope 3 活動資料彙總儀表板。
 */
class ActivityReportDashboardController extends Controller
{
    public function __construct(
        private readonly ActivityDataReportService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);

        $reports = $this->service->paginateAll(
            $request->only(['status', 'push_status', 'report_period', 'supplier_id']),
            $perPage > 0 ? $perPage : 20,
        );

        return response()->json([
            'success' => true,
            'data'    => $reports->items(),
            'meta'    => [
                'current_page' => $reports->currentPage(),
                'last_page'    => $reports->lastPage(),
                'per_page'     => $reports->perPage(),
                'total'        => $reports->total(),
            ],
        ]);
    }

    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->summary(),
        ]);
    }

    public function verify(ActivityDataReport $report): JsonResponse
    {
        $report = $this->service->verify($report);
        return response()->json(['success' => true, 'data' => $report]);
    }

    public function push(ActivityDataReport $report): JsonResponse
    {
        $this->service->retryPush($report);
        return response()->json(['success' => true, 'message' => '推送任務已重新排程']);
    }
}
