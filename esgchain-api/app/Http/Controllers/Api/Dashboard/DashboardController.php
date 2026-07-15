<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service) {}

    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getSummary(auth()->user()),
        ]);
    }

    public function recentActivity(Request $request): JsonResponse
    {
        $days  = (int) $request->input('days', 7);
        $limit = (int) $request->input('limit', 20);
        return response()->json([
            'success' => true,
            'data'    => $this->service->getRecentActivity(min($days, 90), min($limit, 500)),
        ]);
    }

    public function expiringDocs(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getExpiringDocs(),
        ]);
    }

    public function complianceRisk(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getComplianceRisk(),
        ]);
    }

    public function esgScores(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getEsgScores(),
        ]);
    }
}
