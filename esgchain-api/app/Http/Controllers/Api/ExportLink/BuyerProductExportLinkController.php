<?php

namespace App\Http\Controllers\Api\ExportLink;

use App\Http\Controllers\Controller;
use App\Models\BuyerProduct;
use App\Models\BuyerProductTradeGood;
use App\Models\ProductBomLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BuyerProductExportLinkController extends Controller
{
    public function index(string $buyerProductId): JsonResponse
    {
        $product = BuyerProduct::findOrFail($buyerProductId);

        $links = $product->exportLinks()
            ->with(['tradeGood:id,name,product_code,hs_code', 'bomLine:id,material_name'])
            ->get()
            ->map(fn($link) => [
                'id'                => $link->id,
                'buyer_product_id'  => $link->buyer_product_id,
                'trade_good_id'     => $link->trade_good_id,
                'trade_good_name'   => $link->tradeGood?->name,
                'trade_good_code'   => $link->tradeGood?->product_code,
                'trade_good_hs_code'=> $link->tradeGood?->hs_code,
                'relation_type'     => $link->relation_type,
                'bom_line_id'       => $link->bom_line_id,
                'bom_line_material'  => $link->bomLine?->material_name,
                'note'              => $link->note,
                'erp_product_code'  => $link->erp_product_code,
            ]);

        return response()->json(['success' => true, 'data' => $links]);
    }

    public function store(Request $request, string $buyerProductId): JsonResponse
    {
        $product = BuyerProduct::findOrFail($buyerProductId);

        $data = $request->validate([
            'trade_good_id'  => ['required', 'uuid', 'exists:trade_goods,id'],
            'relation_type'  => ['required', Rule::in(['finished_good', 'component', 'equivalent'])],
            'bom_line_id'      => ['nullable', 'uuid', 'exists:product_bom_lines,id'],
            'note'             => ['nullable', 'string', 'max:500'],
            'erp_product_code' => ['nullable', 'string', 'max:100'],
        ]);

        // bom_line_id must belong to this product
        if (!empty($data['bom_line_id'])) {
            $bomLine = ProductBomLine::find($data['bom_line_id']);
            if (!$bomLine || $bomLine->buyer_product_id !== $product->id) {
                return response()->json(['message' => 'bom_line_id 不屬於此產品'], 422);
            }
        }

        $existing = BuyerProductTradeGood::where('buyer_product_id', $product->id)
            ->where('trade_good_id', $data['trade_good_id'])
            ->first();

        if ($existing) {
            return response()->json(['message' => '該出口連結已存在'], 422);
        }

        $link = BuyerProductTradeGood::create(array_merge($data, [
            'buyer_product_id' => $product->id,
        ]));

        $link->load(['tradeGood:id,name,product_code,hs_code', 'bomLine:id,material_name']);

        return response()->json(['success' => true, 'data' => $link], 201);
    }

    public function destroy(string $buyerProductId, string $linkId): JsonResponse
    {
        $link = BuyerProductTradeGood::where('buyer_product_id', $buyerProductId)
            ->findOrFail($linkId);

        $link->delete();

        return response()->json(['success' => true]);
    }
}
