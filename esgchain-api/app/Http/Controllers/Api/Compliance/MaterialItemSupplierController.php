<?php

namespace App\Http\Controllers\Api\Compliance;

use App\Http\Controllers\Controller;
use App\Models\BomLineSupplier;
use App\Models\MaterialItem;
use App\Models\MaterialItemSupplier;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 物料層級核可供應商清單（主/備）：對每個物料只登記一次，取代過去要在每個
 * 使用此物料的產品 BOM 明細各自重複登記同一供應商的作法。
 *
 * 不影響既有 bom_line_suppliers（PCF 計算、風險評分等既有邏輯仍以它為準）；
 * 這裡是新的核可清單來源，透過 applyToProduct() 把清單「套用」到特定產品的
 * BOM 明細時才會寫入 bom_line_suppliers。
 */
class MaterialItemSupplierController extends Controller
{
    public function index(MaterialItem $materialItem): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $materialItem->approvedSuppliers()->with(['supplier:id,name,code,onboarding_stage', 'supplierFacility'])->get(),
        ]);
    }

    public function store(Request $request, MaterialItem $materialItem): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id'          => ['required', 'uuid', 'exists:suppliers,id'],
            'role'                 => ['required', 'in:primary,alternate'],
            'supplier_facility_id' => ['nullable', 'uuid', 'exists:supplier_facilities,id'],
        ]);

        $supplier = Supplier::find($validated['supplier_id']);
        if ($supplier && $supplier->onboarding_stage !== 'certified') {
            return response()->json([
                'success' => false,
                'message' => "此供應商尚未通過認證（onboarding_stage: {$supplier->onboarding_stage}），無法列入物料核可清單",
            ], 422);
        }

        $alreadyLinked = $materialItem->approvedSuppliers()->where('supplier_id', $validated['supplier_id'])->exists();
        if ($alreadyLinked) {
            return response()->json(['success' => false, 'message' => '此供應商已在此物料的核可清單中'], 422);
        }

        if ($validated['role'] === 'primary') {
            $alreadyHasPrimary = $materialItem->approvedSuppliers()->where('role', 'primary')->exists();
            if ($alreadyHasPrimary) {
                return response()->json(['success' => false, 'message' => '每個物料只能有一個主要供應商'], 422);
            }
        }

        // 未指定製程廠區時，若供應商僅有單一廠區，直接視為預設廠區，不強制使用者手動選擇
        $facilityId = $validated['supplier_facility_id'] ?? $supplier?->defaultFacility()?->id;

        $link = $materialItem->approvedSuppliers()->create([
            'supplier_id'          => $validated['supplier_id'],
            'supplier_facility_id' => $facilityId,
            'role'                 => $validated['role'],
            'source'               => 'manual',
            'sort_order'           => $materialItem->approvedSuppliers()->max('sort_order') + 1,
        ]);

        return response()->json(['success' => true, 'data' => $link->load('supplier')], 201);
    }

    public function setRole(Request $request, MaterialItem $materialItem, MaterialItemSupplier $approvedSupplier): JsonResponse
    {
        abort_if($approvedSupplier->material_item_id !== $materialItem->id, 404);

        $validated = $request->validate(['role' => 'required|in:primary,alternate']);

        DB::transaction(function () use ($materialItem, $approvedSupplier, $validated) {
            if ($validated['role'] === 'primary') {
                $materialItem->approvedSuppliers()
                    ->where('role', 'primary')
                    ->where('id', '!=', $approvedSupplier->id)
                    ->update(['role' => 'alternate']);
            }
            $approvedSupplier->update(['role' => $validated['role']]);
        });

        return response()->json(['success' => true, 'data' => $approvedSupplier->fresh()->load('supplier')]);
    }

    public function destroy(MaterialItem $materialItem, MaterialItemSupplier $approvedSupplier): JsonResponse
    {
        abort_if($approvedSupplier->material_item_id !== $materialItem->id, 404);
        $approvedSupplier->delete();

        return response()->json(['success' => true, 'message' => '供應商已從核可清單移除']);
    }

    /**
     * 把物料核可清單套用到指定產品的 BOM 明細（寫入既有 bom_line_suppliers，
     * 讓 PCF 計算/風險評分等既有邏輯照常運作），已存在的供應商關聯不重複寫入。
     */
    public function applyToBomLine(Request $request, MaterialItem $materialItem): JsonResponse
    {
        $validated = $request->validate([
            'bom_line_id' => ['required', 'uuid', 'exists:product_bom_lines,id'],
        ]);

        $bomLine = \App\Models\ProductBomLine::findOrFail($validated['bom_line_id']);
        abort_if($bomLine->material_item_id !== $materialItem->id, 422, '此 BOM 明細的物料與核可清單不符');

        $existingSupplierIds = $bomLine->bomLineSuppliers()->pluck('supplier_id')->all();
        $applied = 0;

        foreach ($materialItem->approvedSuppliers as $approved) {
            if (in_array($approved->supplier_id, $existingSupplierIds, true)) {
                continue;
            }

            BomLineSupplier::create([
                'bom_line_id' => $bomLine->id,
                'supplier_id' => $approved->supplier_id,
                'role'        => $approved->role,
                'source'      => 'manual',
                'sort_order'  => $bomLine->bomLineSuppliers()->max('sort_order') + 1,
            ]);
            $applied++;
        }

        return response()->json([
            'success' => true,
            'message' => "已套用 {$applied} 筆核可供應商至此 BOM 明細",
            'data'    => $bomLine->fresh()->load('bomLineSuppliers.supplier'),
        ]);
    }
}
