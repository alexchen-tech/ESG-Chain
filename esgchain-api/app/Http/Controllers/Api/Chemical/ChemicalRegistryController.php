<?php

namespace App\Http\Controllers\Api\Chemical;

use App\Http\Controllers\Controller;
use App\Services\Chemical\ChemicalRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChemicalRegistryController extends Controller
{
    public function __construct(private ChemicalRegistryService $service) {}

    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['cas_no' => 'required|string|max:15']);
        $chemical = $this->service->lookup($request->input('cas_no'));

        if (!$chemical) {
            return response()->json(['success' => false, 'message' => '查無此 CAS No.'], 404);
        }

        return response()->json(['success' => true, 'data' => $chemical]);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2']);
        $results = $this->service->search($request->input('q'));
        return response()->json(['success' => true, 'data' => $results]);
    }
}
