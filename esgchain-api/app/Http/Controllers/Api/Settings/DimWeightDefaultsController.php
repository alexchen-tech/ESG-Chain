<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DimWeightDefaultsController extends Controller
{
    public function __construct(private readonly SystemSettingsService $service) {}

    /** GET /api/v1/settings/dim-weight-defaults */
    public function show(): JsonResponse
    {
        return response()->json([
            'success'     => true,
            'dim_weights' => $this->service->getDimWeightDefaults(),
        ]);
    }

    /** PUT /api/v1/settings/dim-weight-defaults */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'dim_weights'      => ['required', 'array'],
            'dim_weights.E1'   => ['required', 'numeric', 'min:0', 'max:1'],
            'dim_weights.E2'   => ['required', 'numeric', 'min:0', 'max:1'],
            'dim_weights.E3'   => ['required', 'numeric', 'min:0', 'max:1'],
            'dim_weights.E4'   => ['required', 'numeric', 'min:0', 'max:1'],
            'dim_weights.E5'   => ['required', 'numeric', 'min:0', 'max:1'],
            'dim_weights.E6'   => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        try {
            $this->service->setDimWeightDefaults($request->dim_weights);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success'     => true,
            'dim_weights' => $this->service->getDimWeightDefaults(),
            'message'     => '已儲存，新建立的 Series 將使用此預設加權',
        ]);
    }
}
