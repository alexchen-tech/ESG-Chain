<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\SupplierGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierGroupController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => SupplierGroup::orderBy('name')->get(),
            'message' => '',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'description'          => ['nullable', 'string'],
            'required_doc_types'   => ['nullable', 'array'],
            'required_doc_types.*' => ['string', 'in:SMETA_AUDIT,ISO_9001,FACTORY_AUDIT,HIGG_FEM,OEKO_TEX,BSCI,ZDHC_MRSL'],
        ]);

        $group = SupplierGroup::create($validated);

        return response()->json(['success' => true, 'data' => $group, 'message' => '分組已建立'], 201);
    }

    public function update(Request $request, SupplierGroup $group): JsonResponse
    {
        $validated = $request->validate([
            'name'                 => ['sometimes', 'string', 'max:255'],
            'description'          => ['sometimes', 'nullable', 'string'],
            'required_doc_types'   => ['sometimes', 'nullable', 'array'],
            'required_doc_types.*' => ['string', 'in:SMETA_AUDIT,ISO_9001,FACTORY_AUDIT,HIGG_FEM,OEKO_TEX,BSCI,ZDHC_MRSL'],
        ]);

        $group->update($validated);

        return response()->json(['success' => true, 'data' => $group->fresh(), 'message' => '分組已更新']);
    }

    public function destroy(SupplierGroup $group): JsonResponse
    {
        $group->delete();

        return response()->json(['success' => true, 'data' => null, 'message' => '分組已刪除']);
    }

    public function inferredMaterialGroups(SupplierGroup $group): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $group->inferredMaterialGroups(),
        ]);
    }
}
