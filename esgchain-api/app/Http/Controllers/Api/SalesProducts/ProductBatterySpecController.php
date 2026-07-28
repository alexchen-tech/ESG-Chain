<?php

namespace App\Http\Controllers\Api\SalesProducts;

use App\Http\Controllers\Controller;
use App\Models\SalesProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductBatterySpecController extends Controller
{
    public function show(SalesProduct $salesProduct): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $salesProduct->batterySpec]);
    }

    public function upsert(Request $request, SalesProduct $salesProduct): JsonResponse
    {
        $validated = $request->validate([
            'battery_category'                => ['nullable', 'in:portable,industrial,ev,lmt'],
            'chemistry'                        => ['nullable', 'string', 'max:60'],
            'rated_capacity_ah'                => ['nullable', 'numeric', 'min:0'],
            'rated_voltage_v'                  => ['nullable', 'numeric', 'min:0'],
            'weight_kg'                        => ['nullable', 'numeric', 'min:0'],
            'lithium_recycled_content_ratio'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cobalt_recycled_content_ratio'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'nickel_recycled_content_ratio'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lead_recycled_content_ratio'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cycle_life'                       => ['nullable', 'integer', 'min:0'],
            'expected_lifetime_years'          => ['nullable', 'integer', 'min:0'],
            'discharge_efficiency_ratio'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'initial_capacity_soh_note'        => ['nullable', 'string', 'max:200'],
            'operating_temp_range'             => ['nullable', 'string', 'max:60'],
        ]);

        $spec = $salesProduct->batterySpec()->updateOrCreate(
            ['sales_product_id' => $salesProduct->id],
            $validated,
        );

        return response()->json(['success' => true, 'data' => $spec, 'message' => '電池規格已儲存']);
    }
}
