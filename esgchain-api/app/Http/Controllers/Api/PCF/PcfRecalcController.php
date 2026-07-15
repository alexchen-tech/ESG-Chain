<?php

namespace App\Http\Controllers\Api\PCF;

use App\Http\Controllers\Controller;
use App\Models\SalesProduct;
use App\Services\PCF\PcfCalculationService;
use Illuminate\Http\JsonResponse;

class PcfRecalcController extends Controller
{
    public function __construct(
        private readonly PcfCalculationService $calcService
    ) {}

    public function recalc(string $salesProductId): JsonResponse
    {
        $product  = SalesProduct::findOrFail($salesProductId);
        $snapshot = $this->calcService->snapshot($product);

        return response()->json(['data' => $snapshot], 201);
    }
}
