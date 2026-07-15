<?php

namespace App\Http\Controllers\Api\Chemical;

use App\Http\Controllers\Controller;
use App\Models\ChemicalComplianceAlert;
use App\Models\MaterialItem;
use App\Services\Chemical\ChemicalComplianceScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChemicalComplianceAlertController extends Controller
{
    public function __construct(private ChemicalComplianceScanService $scanService) {}

    public function index(Request $request): JsonResponse
    {
        $query = ChemicalComplianceAlert::with(['materialItem', 'chemical'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('material_item_id'), fn ($q, $id) => $q->where('material_item_id', $id))
            ->latest();

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($request->input('per_page', 20)),
        ]);
    }

    public function acknowledge(Request $request, ChemicalComplianceAlert $alert): JsonResponse
    {
        $request->validate(['notes' => 'nullable|string|max:1000']);

        $alert = $this->scanService->acknowledge(
            $alert->id,
            (string) auth()->id(),
            $request->input('notes'),
        );

        return response()->json(['success' => true, 'data' => $alert]);
    }

    public function scan(string $materialItemId): JsonResponse
    {
        $alerts = $this->scanService->scanMaterialItem($materialItemId);
        return response()->json([
            'success'    => true,
            'new_alerts' => count($alerts),
            'data'       => $alerts,
        ]);
    }
}
