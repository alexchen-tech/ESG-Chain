<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarbonPriceController extends Controller
{
    private const KEY = 'carbon_price_eur';
    private const DEFAULT = '65.00';

    public function show(): JsonResponse
    {
        $setting = SystemSetting::find(self::KEY);

        return response()->json([
            'success' => true,
            'data' => [
                'carbon_price_eur' => (float) ($setting?->value ?? self::DEFAULT),
                'is_default'       => $setting === null,
                'updated_at'       => $setting?->updated_at,
                'updated_by'       => $setting?->updated_by
                    ? User::find($setting->updated_by)?->name
                    : null,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        if (!auth()->user()?->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => '僅限系統管理員操作'], 403);
        }

        $validated = $request->validate([
            'carbon_price_eur' => ['required', 'numeric', 'min:0.01'],
        ]);

        SystemSetting::set(
            self::KEY,
            number_format((float) $validated['carbon_price_eur'], 2, '.', ''),
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'data'    => ['carbon_price_eur' => (float) $validated['carbon_price_eur']],
            'message' => '碳價假設已更新',
        ]);
    }
}
