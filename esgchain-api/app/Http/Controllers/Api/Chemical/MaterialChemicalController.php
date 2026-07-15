<?php

namespace App\Http\Controllers\Api\Chemical;

use App\Http\Controllers\Controller;
use App\Models\MaterialItem;
use App\Models\MaterialItemChemical;
use App\Services\Chemical\MaterialChemicalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialChemicalController extends Controller
{
    public function __construct(private MaterialChemicalService $service) {}

    public function index(string $materialItemId): JsonResponse
    {
        $items = $this->service->list($materialItemId);
        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request, string $materialItemId): JsonResponse
    {
        $data = $request->validate([
            'cas_no'              => 'required|string|max:15',
            'weight_percentage'   => 'nullable|numeric|min:0|max:100',
            'reporting_threshold' => 'nullable|numeric|min:0|max:100',
            'source'              => 'nullable|in:portal_supplier,buyer_input,ai_estimated',
        ]);

        $record = $this->service->create($materialItemId, $data);
        return response()->json(['success' => true, 'data' => $record], 201);
    }

    public function destroy(string $materialItemId, MaterialItemChemical $chemical): JsonResponse
    {
        $this->service->delete($chemical);
        return response()->json(['success' => true]);
    }
}
