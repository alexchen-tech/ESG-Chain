<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\TradeGood;
use App\Models\TradeGoodSupplierEmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalTradeGoodController extends Controller
{
    public function index(): JsonResponse
    {
        $supplierId = Auth::user()->supplier_id;
        abort_if(!$supplierId, 403);

        $tradeGoods = TradeGood::whereHas('tradeGoodSuppliers', fn($q) =>
            $q->where('supplier_id', $supplierId)
        )->with(['emissionReports' => fn($q) =>
            $q->where('supplier_id', $supplierId)->orderByDesc('reported_at')->limit(1)
        ])->get();

        $data = $tradeGoods->map(function ($tg) {
            $latest = $tg->emissionReports->first();
            return [
                'id'               => $tg->id,
                'name'             => $tg->name,
                'product_code'     => $tg->product_code,
                'hs_code'          => $tg->hs_code,
                'is_cbam_applicable' => $tg->is_cbam_applicable,
                'cbam_category'    => $tg->cbam_category,
                'reported'         => $latest !== null,
                'latest_emissions' => $latest?->emissions_value,
                'reported_at'      => $latest?->reported_at,
                'confirmed_at'     => $latest?->confirmed_at,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function reportEmission(Request $request, TradeGood $tradeGood): JsonResponse
    {
        $supplierId = Auth::user()->supplier_id;
        abort_if(!$supplierId, 403);

        // 確認此供應商確實被關聯到此 TradeGood
        abort_unless(
            $tradeGood->tradeGoodSuppliers()->where('supplier_id', $supplierId)->exists(),
            403,
            '您未被關聯至此貿易商品'
        );

        $validated = $request->validate([
            'emissions_value'    => ['required', 'numeric', 'min:0.0001'],
            'calculation_note'   => ['nullable', 'string', 'max:1000'],
        ]);

        $emission = TradeGoodSupplierEmission::create([
            'trade_good_id'    => $tradeGood->id,
            'supplier_id'      => $supplierId,
            'emissions_value'  => $validated['emissions_value'],
            'calculation_note' => $validated['calculation_note'] ?? null,
            'reported_at'      => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $emission,
            'message' => '碳排數值已提交',
        ], 201);
    }
}
