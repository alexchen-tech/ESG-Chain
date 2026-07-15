<?php

namespace App\Http\Controllers\Api\Compliance;

use App\Http\Controllers\Controller;
use App\Models\MaterialGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialGroupController extends Controller
{
    public function index(): JsonResponse
    {
        $groups = MaterialGroup::orderBy('name')->get();

        return response()->json(['success' => true, 'data' => $groups]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:100', 'unique:material_groups,name'],
            'description'        => ['nullable', 'string'],
            'hs_code_prefixes'   => ['required', 'array'],
            'hs_code_prefixes.*' => ['string'],
            'required_doc_types' => ['required', 'array'],
            'required_doc_types.*' => ['string', 'in:UFLPA_DECLARATION,EUDR_DDS,CMRT,SDS,CE_DOC,ORIGIN_CERT,OTHER'],
        ]);

        $group = MaterialGroup::create(array_merge($validated, ['is_system' => false]));

        return response()->json(['success' => true, 'data' => $group], 201);
    }

    public function update(Request $request, MaterialGroup $materialGroup): JsonResponse
    {
        $validated = $request->validate([
            'name'               => ['sometimes', 'string', 'max:100', 'unique:material_groups,name,' . $materialGroup->id],
            'description'        => ['sometimes', 'nullable', 'string'],
            'hs_code_prefixes'   => ['sometimes', 'array'],
            'hs_code_prefixes.*' => ['string'],
            'required_doc_types'   => ['sometimes', 'array'],
            'required_doc_types.*' => ['string', 'in:UFLPA_DECLARATION,EUDR_DDS,CMRT,SDS,CE_DOC,ORIGIN_CERT,OTHER'],
        ]);

        $materialGroup->update($validated);

        return response()->json(['success' => true, 'data' => $materialGroup->fresh()]);
    }

    public function destroy(MaterialGroup $materialGroup): JsonResponse
    {
        if ($materialGroup->is_system) {
            return response()->json([
                'success' => false,
                'message' => '系統預載物料群組不可刪除',
            ], 422);
        }

        $itemCount    = \App\Models\MaterialItem::where('material_group_id', $materialGroup->id)->count();
        $bomLineCount = \App\Models\ProductBomLine::where('material_group_id', $materialGroup->id)->count();

        if ($itemCount > 0 || $bomLineCount > 0) {
            return response()->json([
                'success'        => false,
                'message'        => "此物料群組被 {$itemCount} 個料號、{$bomLineCount} 條 BOM 明細使用中，無法刪除",
                'item_count'     => $itemCount,
                'bom_line_count' => $bomLineCount,
            ], 422);
        }

        $materialGroup->delete();

        return response()->json(['success' => true, 'message' => '物料群組已刪除']);
    }
}
