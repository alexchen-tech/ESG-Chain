<?php

namespace App\Http\Controllers\Api\Compliance;

use App\Http\Controllers\Controller;
use App\Models\SalesProduct;
use App\Services\Circularity\CircularityCalculationService;
use Illuminate\Http\JsonResponse;

class ProductCircularityController extends Controller
{
    public function __construct(
        private readonly CircularityCalculationService $calcService
    ) {}

    public function latest(string $salesProductId): JsonResponse
    {
        $product  = SalesProduct::findOrFail($salesProductId);
        $snapshot = $product->latestCircularitySnapshot();

        return response()->json(['success' => true, 'data' => $snapshot]);
    }

    public function recalc(string $salesProductId): JsonResponse
    {
        $product  = SalesProduct::findOrFail($salesProductId);
        $snapshot = $this->calcService->snapshot($product);

        return response()->json(['success' => true, 'data' => $snapshot], 201);
    }
}
