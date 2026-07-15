<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CalculatePathRiskJob;
use App\Models\TradeGood;
use App\Models\TradeGoodPathRisk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportRiskMatrixController extends Controller
{
    private const MARKETS = ['EU', 'US', 'APAC', 'GB', 'JP'];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'market'        => ['sometimes', 'string', 'in:EU,US,APAC,GB,JP'],
            'trade_good_id' => ['sometimes', 'uuid'],
        ]);

        $market      = $request->input('market');
        $tradeGoodId = $request->input('trade_good_id');

        // 取得商品清單
        $query = TradeGood::query();
        if ($tradeGoodId) {
            $query->where('id', $tradeGoodId);
        }
        $tradeGoods = $query->select('id', 'name', 'product_code', 'hs_code')->get();

        $markets = $market ? [$market] : self::MARKETS;

        // 預載所有相關快取
        $cachedRisks = TradeGoodPathRisk::whereIn('trade_good_id', $tradeGoods->pluck('id'))
            ->when($market, fn ($q) => $q->where('market', $market))
            ->get()
            ->keyBy(fn ($r) => "{$r->trade_good_id}:{$r->market}");

        // 格式：products[] + matrix{ product_id: { market: {...} } }
        $products = $tradeGoods->map(fn ($tg) => [
            'id'           => $tg->id,
            'name'         => $tg->name,
            'product_code' => $tg->product_code,
            'hs_code'      => $tg->hs_code,
        ])->values();

        $matrix = [];
        foreach ($tradeGoods as $tg) {
            $matrix[$tg->id] = [];
            foreach ($markets as $mkt) {
                $cacheKey = "{$tg->id}:{$mkt}";
                $cached   = $cachedRisks->get($cacheKey);

                if ($cached && !$cached->isExpired()) {
                    $matrix[$tg->id][$mkt] = [
                        'path_risk_score' => $cached->path_risk_score,
                        'risk_level'      => $cached->risk_level,
                        'has_data_gap'    => $cached->has_data_gap,
                        'calculated_at'   => $cached->calculated_at?->toIso8601String(),
                        'status'          => 'ready',
                    ];
                } else {
                    // 快取缺失或過期，觸發非同步計算
                    CalculatePathRiskJob::dispatch($tg->id, $mkt);
                    $matrix[$tg->id][$mkt] = [
                        'path_risk_score' => $cached?->path_risk_score,
                        'risk_level'      => $cached?->risk_level,
                        'has_data_gap'    => $cached?->has_data_gap ?? false,
                        'calculated_at'   => $cached?->calculated_at?->toIso8601String(),
                        'status'          => $cached ? 'stale' : 'calculating',
                    ];
                }
            }
        }

        return response()->json(['products' => $products, 'matrix' => $matrix]);
    }
}
