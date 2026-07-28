<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\SupplierDisclosure;
use App\Models\SupplierDisclosureField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PortalDisclosureController extends Controller
{
    /**
     * GET /api/v1/portal/disclosures
     * 供應商永續 KPI 填報：依欄位（field）分組，每個欄位底下帶歷年填報紀錄。
     */
    public function index(Request $request): JsonResponse
    {
        $supplierId = $request->user()->supplier_id;
        abort_if(!$supplierId, 403, '此帳號未綁定供應商');

        $records = SupplierDisclosure::where('supplier_id', $supplierId)->get();
        $recordsBySlug = $records->groupBy('field_slug');

        $fields = SupplierDisclosureField::orderBy('slug')->get()->map(function (SupplierDisclosureField $field) use ($recordsBySlug) {
            $recs = $recordsBySlug->get($field->slug, collect());

            return [
                'slug'      => $field->slug,
                'label'     => $field->label,
                'data_type' => $field->data_type,
                'unit'      => $field->unit,
                'options'   => $field->options,
                'records'   => $recs->map(fn (SupplierDisclosure $r) => [
                    'period_year'   => $r->period_year,
                    'numeric_value' => $r->numeric_value,
                    'boolean_value' => $r->boolean_value,
                    'text_value'    => $r->text_value,
                    'source'        => $r->source,
                    'source_saq_id' => $r->source_saq_id,
                    'updated_at'    => $r->updated_at?->toISOString(),
                ])->values(),
            ];
        });

        return response()->json(['success' => true, 'data' => $fields]);
    }

    /**
     * POST /api/v1/portal/disclosures
     * upsert 單一欄位×年度的填報值。若原本是 source=saq_sync（問卷同步而來），
     * 手動覆蓋後改記為 source=manual，並回傳 overwritten_saq_sync 讓前端提示使用者。
     */
    public function store(Request $request): JsonResponse
    {
        $supplierId = $request->user()->supplier_id;
        abort_if(!$supplierId, 403, '此帳號未綁定供應商');

        $field = SupplierDisclosureField::find($request->input('field_slug'));
        abort_if(!$field, 422, '不存在的填報欄位');

        $valueRule = match ($field->data_type) {
            'boolean'       => ['required', 'boolean'],
            'single_choice' => ['required', 'string', Rule::in(array_column($field->options ?? [], 'value'))],
            default         => ['required', 'numeric'],
        };

        $validated = $request->validate([
            'field_slug'  => ['required', 'string'],
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'value'       => $valueRule,
        ]);

        $existing = SupplierDisclosure::where('supplier_id', $supplierId)
            ->where('field_slug', $validated['field_slug'])
            ->where('period_year', $validated['period_year'])
            ->first();

        $overwrittenSaqSync = $existing && $existing->source === 'saq_sync';

        $payload = [
            'source'         => 'manual',
            'source_saq_id'  => null,
            'numeric_value'  => $field->data_type === 'numeric' ? (float) $validated['value'] : null,
            'boolean_value'  => $field->data_type === 'boolean' ? (bool) $validated['value'] : null,
            'text_value'     => $field->data_type === 'single_choice' ? (string) $validated['value'] : null,
        ];

        SupplierDisclosure::updateOrCreate(
            [
                'supplier_id' => $supplierId,
                'field_slug'  => $validated['field_slug'],
                'period_year' => $validated['period_year'],
            ],
            $payload,
        );

        return response()->json(['success' => true, 'overwritten_saq_sync' => $overwrittenSaqSync]);
    }
}
