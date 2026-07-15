<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierDisclosure;
use App\Models\SupplierDisclosureField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierDisclosureController extends Controller
{
    /**
     * GET /api/v1/suppliers/{supplier}/disclosure-profile
     * 回傳依 field_slug 分組的歷年填報值。
     * supplier / sup_esg 角色只能存取自己的 disclosure。
     */
    public function profile(Request $request, Supplier $supplier): JsonResponse
    {
        $user = $request->user();

        if ($user->hasAnyRole(['supplier', 'sup_esg'])) {
            if ($user->supplier_id !== $supplier->id) {
                return response()->json(['success' => false, 'message' => '無權存取'], 403);
            }
        }

        $records = SupplierDisclosure::where('supplier_id', $supplier->id)
            ->orderBy('field_slug')
            ->orderBy('period_year')
            ->get(['field_slug', 'period_year', 'numeric_value', 'boolean_value', 'text_value', 'source', 'source_saq_id', 'verified_at']);

        $fields = SupplierDisclosureField::all()->keyBy('slug');

        $grouped = [];
        foreach ($records as $rec) {
            $field = $fields->get($rec->field_slug);
            $grouped[$rec->field_slug][] = [
                'period_year'    => $rec->period_year,
                'numeric_value'  => $rec->numeric_value,
                'boolean_value'  => $rec->boolean_value,
                'text_value'     => $rec->text_value,
                'source'         => $rec->source,
                'source_saq_id'  => $rec->source_saq_id,
                'verified_at'    => $rec->verified_at,
                'unit'           => $field?->unit,
                'label'          => $field?->label,
                'data_type'      => $field?->data_type,
            ];
        }

        return response()->json([
            'success'     => true,
            'data' => [
                'supplier_id' => $supplier->id,
                'disclosures' => $grouped,
            ],
        ]);
    }
}
