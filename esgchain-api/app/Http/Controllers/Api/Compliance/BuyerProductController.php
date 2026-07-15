<?php

namespace App\Http\Controllers\Api\Compliance;

use App\Http\Controllers\Controller;
use App\Models\BuyerProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuyerProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = BuyerProduct::withCount('bomLines')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:200'],
            'product_code' => ['nullable', 'string', 'max:100'],
            'description'  => ['nullable', 'string'],
        ]);

        $product = BuyerProduct::create(array_merge($validated, ['applicable_regulations' => []]));

        return response()->json(['success' => true, 'data' => $product], 201);
    }

    public function update(Request $request, BuyerProduct $buyerProduct): JsonResponse
    {
        $validated = $request->validate([
            'name'                   => ['sometimes', 'string', 'max:200'],
            'product_code'           => ['sometimes', 'nullable', 'string', 'max:100'],
            'description'            => ['sometimes', 'nullable', 'string'],
            'applicable_regulations' => ['sometimes', 'array'],
            'applicable_regulations.*' => ['string', 'in:EUDR,UFLPA,CMRT,REACH,CE,ESPR'],
        ]);

        $buyerProduct->update($validated);

        return response()->json(['success' => true, 'data' => $buyerProduct->fresh()]);
    }

    public function destroy(BuyerProduct $buyerProduct): JsonResponse
    {
        $buyerProduct->delete();

        return response()->json(['success' => true, 'message' => '產品已刪除']);
    }
}
