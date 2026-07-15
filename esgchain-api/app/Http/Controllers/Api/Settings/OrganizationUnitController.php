<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\OrganizationUnit;
use App\Services\OrganizationUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationUnitController extends Controller
{
    public function __construct(private OrganizationUnitService $service) {}

    public function index(): JsonResponse
    {
        $units = $this->service->getAll();

        return response()->json(['success' => true, 'data' => $units]);
    }

    public function tree(): JsonResponse
    {
        $tree = $this->service->getTree();

        return response()->json(['success' => true, 'data' => $tree]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100',
            'code'         => 'required|string|max:32|unique:organization_units,code',
            'type'         => 'required|in:headquarters,subsidiary,business_unit,department,branch',
            'parent_id'    => 'nullable|uuid|exists:organization_units,id',
            'country_code' => 'nullable|string|size:2',
            'sort_order'   => 'nullable|integer',
        ]);

        $unit = $this->service->create($validated);

        return response()->json(['success' => true, 'data' => $unit], 201);
    }

    public function update(Request $request, OrganizationUnit $unit): JsonResponse
    {
        $validated = $request->validate([
            'name'         => 'sometimes|string|max:100',
            'code'         => 'sometimes|string|max:32|unique:organization_units,code,' . $unit->id,
            'country_code' => 'sometimes|string|size:2',
            'is_active'    => 'sometimes|boolean',
            'sort_order'   => 'sometimes|integer',
        ]);

        $unit = $this->service->update($unit, $validated);

        return response()->json(['success' => true, 'data' => $unit]);
    }

    public function destroy(OrganizationUnit $unit): JsonResponse
    {
        $this->service->delete($unit);

        return response()->json(['success' => true, 'message' => '組織單位已刪除']);
    }
}
