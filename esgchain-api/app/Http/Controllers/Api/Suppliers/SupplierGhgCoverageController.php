<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Http\Controllers\Controller;
use App\Services\Disclosure\SupplierGhgCoverageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierGhgCoverageController extends Controller
{
    public function __construct(
        private readonly SupplierGhgCoverageService $service,
    ) {}

    /**
     * GET /api/v1/suppliers/ghg-coverage?period_year=2026&sort_by=scope1&sort_dir=desc&page=1&per_page=20
     * 供應商範疇一/二/三碳盤查覆蓋度稽核清單。
     * 排序在整份資料上執行後才切頁，確保「依範疇一排放量排序」等操作是全體排序而非當頁排序。
     */
    public function index(Request $request): JsonResponse
    {
        $periodYear = $request->filled('period_year') ? (int) $request->input('period_year') : null;
        $sortBy = $request->filled('sort_by') ? (string) $request->input('sort_by') : null;
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, (int) $request->input('per_page', 20));

        $result = $this->service->coverageListPaginated($periodYear, $sortBy, $sortDir, $page, $perPage);

        return response()->json([
            'success' => true,
            'data'    => $result['data'],
            'pagination' => [
                'current_page' => $result['current_page'],
                'per_page'     => $result['per_page'],
                'total'        => $result['total'],
                'last_page'    => $result['last_page'],
            ],
        ]);
    }

    /**
     * GET /api/v1/suppliers/ghg-coverage/by-group?period_year=2026
     * 依供應商群組彙總碳盤查覆蓋度，含總覆蓋率（overall）。
     */
    public function byGroup(Request $request): JsonResponse
    {
        $periodYear = $request->filled('period_year') ? (int) $request->input('period_year') : null;

        return response()->json([
            'success' => true,
            'data'    => $this->service->coverageByGroup($periodYear),
        ]);
    }

    /**
     * GET /api/v1/suppliers/ghg-coverage/trend
     * 各年度整體碳盤查覆蓋率成長趨勢。
     */
    public function trend(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->coverageTrend(),
        ]);
    }
}
