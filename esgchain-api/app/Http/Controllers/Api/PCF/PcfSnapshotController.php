<?php

namespace App\Http\Controllers\Api\PCF;

use App\Http\Controllers\Controller;
use App\Models\PcfSnapshot;
use App\Models\SalesProduct;
use App\Services\PCF\PcfCalculationService;
use Illuminate\Http\JsonResponse;

class PcfSnapshotController extends Controller
{
    public function __construct(
        private readonly PcfCalculationService $calcService
    ) {}

    public function latest(string $salesProductId): JsonResponse
    {
        $product  = SalesProduct::findOrFail($salesProductId);
        $snapshot = $product->latestPcfSnapshot();

        if (!$snapshot) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $snapshot]);
    }

    public function show(string $snapshotId): JsonResponse
    {
        $snapshot = PcfSnapshot::findOrFail($snapshotId);
        return response()->json(['data' => $snapshot]);
    }
}
