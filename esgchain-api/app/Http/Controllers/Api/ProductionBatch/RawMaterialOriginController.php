<?php

namespace App\Http\Controllers\Api\ProductionBatch;

use App\Http\Controllers\Controller;
use App\Models\ProductionBatch;
use App\Models\RawMaterialOrigin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RawMaterialOriginController extends Controller
{
    public function store(Request $request, string $batchId): JsonResponse
    {
        $batch = ProductionBatch::findOrFail($batchId);

        $data = $request->validate([
            'material_name'     => ['required', 'string', 'max:200'],
            'origin_country'    => ['required', 'string', 'size:2'],
            'facility_name'     => ['nullable', 'string', 'max:200'],
            'gps_lat'           => ['nullable', 'numeric', 'between:-90,90'],
            'gps_lng'           => ['nullable', 'numeric', 'between:-180,180'],
            'harvest_year'      => ['nullable', 'integer', 'digits:4'],
            'certification_ref' => ['nullable', 'string', 'max:200'],
            'bom_line_id'       => ['nullable', 'uuid', 'exists:product_bom_lines,id'],
        ]);

        $origin = $batch->rawMaterialOrigins()->create($data);

        return response()->json(['success' => true, 'data' => $origin], 201);
    }

    public function update(Request $request, string $batchId, string $id): JsonResponse
    {
        $origin = RawMaterialOrigin::where('production_batch_id', $batchId)->findOrFail($id);

        $data = $request->validate([
            'material_name'     => ['sometimes', 'string', 'max:200'],
            'origin_country'    => ['sometimes', 'string', 'size:2'],
            'facility_name'     => ['nullable', 'string', 'max:200'],
            'gps_lat'           => ['nullable', 'numeric', 'between:-90,90'],
            'gps_lng'           => ['nullable', 'numeric', 'between:-180,180'],
            'harvest_year'      => ['nullable', 'integer', 'digits:4'],
            'certification_ref' => ['nullable', 'string', 'max:200'],
            'bom_line_id'       => ['nullable', 'uuid', 'exists:product_bom_lines,id'],
        ]);

        $origin->update($data);

        return response()->json(['success' => true, 'data' => $origin]);
    }

    public function destroy(string $batchId, string $id): JsonResponse
    {
        RawMaterialOrigin::where('production_batch_id', $batchId)->findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
