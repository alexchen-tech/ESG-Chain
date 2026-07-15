<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\MarketDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketDefinitionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => MarketDefinition::orderBy('is_system', 'desc')->orderBy('label')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'        => ['required', 'string', 'max:50', 'unique:market_definitions,code', 'regex:/^[A-Z_]+$/'],
            'label'       => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $market = MarketDefinition::create($validated);

        return response()->json(['success' => true, 'data' => $market], 201);
    }

    public function update(Request $request, MarketDefinition $marketDefinition): JsonResponse
    {
        $validated = $request->validate([
            'label'       => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $marketDefinition->update($validated);

        return response()->json(['success' => true, 'data' => $marketDefinition->fresh()]);
    }

    public function destroy(MarketDefinition $marketDefinition): JsonResponse
    {
        if ($marketDefinition->is_system) {
            return response()->json([
                'success' => false,
                'message' => '系統預載市場定義不可刪除',
            ], 422);
        }

        $marketDefinition->delete();

        return response()->json(['success' => true, 'message' => '目標市場已刪除']);
    }
}
