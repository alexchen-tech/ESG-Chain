<?php

namespace App\Http\Controllers\Api\Compliance;

use App\Http\Controllers\Controller;
use App\Services\PCF\Scope3Category1Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Scope3Category1Controller extends Controller
{
    public function __construct(
        private readonly Scope3Category1Service $service,
    ) {}

    public function materialRollup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to'   => ['required', 'date', 'after_or_equal:period_from'],
            'page'        => ['nullable', 'integer', 'min:1'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->service->materialRollup(
                $validated['period_from'],
                $validated['period_to'],
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20)
            ),
        ]);
    }

    public function drill(string $materialItemId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to'   => ['required', 'date', 'after_or_equal:period_from'],
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->service->materialDrill($materialItemId, $validated['period_from'], $validated['period_to']),
        ]);
    }
}
