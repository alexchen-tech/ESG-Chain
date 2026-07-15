<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierFacility;
use App\Services\Supplier\SupplierFacilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierFacilityController extends Controller
{
    public function __construct(
        private readonly SupplierFacilityService $service
    ) {}

    public function index(Supplier $supplier): JsonResponse
    {
        return response()->json(['data' => $this->service->list($supplier->id)]);
    }

    public function store(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'country'       => ['nullable', 'string', 'size:2'],
            'address'       => ['nullable', 'string'],
            'facility_type' => ['nullable', 'in:manufacturing,warehouse,office,other'],
            'energy_types'  => ['nullable', 'array'],
            'main_products' => ['nullable', 'string'],
        ]);

        $facility = $this->service->create($supplier->id, $validated);
        return response()->json(['data' => $facility], 201);
    }

    public function update(Request $request, Supplier $supplier, SupplierFacility $facility): JsonResponse
    {
        $validated = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'country'       => ['nullable', 'string', 'size:2'],
            'address'       => ['nullable', 'string'],
            'facility_type' => ['nullable', 'in:manufacturing,warehouse,office,other'],
            'energy_types'  => ['nullable', 'array'],
            'main_products' => ['nullable', 'string'],
            'is_active'     => ['boolean'],
        ]);

        $facility = $this->service->update($facility, $validated);
        return response()->json(['data' => $facility]);
    }
}
