<?php

namespace App\Http\Controllers\Api\PCF;

use App\Http\Controllers\Controller;
use App\Services\PCF\MaterialEmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialEmissionController extends Controller
{
    public function __construct(
        private readonly MaterialEmissionService $service
    ) {}

    public function index(string $materialItemId): JsonResponse
    {
        $grouped = $this->service->listForMaterial($materialItemId);
        return response()->json(['data' => $grouped]);
    }

    public function store(Request $request, string $materialItemId): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id'        => 'nullable|uuid',
            'emissions_value'    => 'required|numeric|min:0',
            'calculation_method' => 'nullable|string|max:100',
            'reported_period'    => 'nullable|string|max:20',
        ]);

        $emission = $this->service->record(
            $materialItemId,
            $validated['supplier_id'] ?? null,
            $validated,
            'buyer-input'
        );

        return response()->json(['data' => $emission], 201);
    }

    public function flag(Request $request, string $emissionId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $emission = $this->service->flag($emissionId, $validated['reason']);
        return response()->json(['data' => $emission]);
    }

    public function unflag(string $emissionId): JsonResponse
    {
        $emission = $this->service->unflag($emissionId);
        return response()->json(['data' => $emission]);
    }
}
