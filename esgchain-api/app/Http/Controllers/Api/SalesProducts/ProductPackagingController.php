<?php

namespace App\Http\Controllers\Api\SalesProducts;

use App\Http\Controllers\Controller;
use App\Models\SalesProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductPackagingController extends Controller
{
    public function show(SalesProduct $salesProduct): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $salesProduct->packaging]);
    }

    public function upsert(Request $request, SalesProduct $salesProduct): JsonResponse
    {
        $validated = $request->validate([
            'recycled_content_ratio' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'recyclable'             => ['nullable', 'boolean'],
            'reusable'               => ['nullable', 'boolean'],
            'material_description'   => ['nullable', 'string', 'max:255'],
            'notes'                  => ['nullable', 'string'],
        ]);

        $packaging = $salesProduct->packaging()->updateOrCreate(
            ['sales_product_id' => $salesProduct->id],
            $validated,
        );

        return response()->json(['success' => true, 'data' => $packaging, 'message' => '包材資訊已儲存']);
    }
}
