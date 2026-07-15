<?php

namespace App\Http\Controllers\Api\TradeGoods;

use App\Http\Controllers\Controller;
use App\Models\TradeGood;
use App\Services\Compliance\MarketComplianceChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeGoodMarketComplianceController extends Controller
{
    public function __construct(private readonly MarketComplianceChecker $checker) {}

    public function batch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'market'         => ['required', 'string', 'in:EU,US,NA,APAC,GB,JP'],
            'trade_good_ids' => ['required', 'array', 'max:100'],
            'trade_good_ids.*' => ['string', 'uuid'],
        ]);

        $results = $this->checker->checkBatch($validated['trade_good_ids'], $validated['market']);

        return response()->json(['data' => $results]);
    }
}
