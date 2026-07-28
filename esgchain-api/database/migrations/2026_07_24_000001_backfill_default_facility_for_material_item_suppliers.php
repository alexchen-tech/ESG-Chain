<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 回填既有 material_item_suppliers 的 supplier_facility_id：僅在該供應商剛好只有
 * 一個廠區時才回填（視為預設廠區），避免多廠區時錯誤猜測用哪一個。
 * 對應 MaterialItemSupplierController::store() 這次新增的「單一廠區即預設」邏輯，
 * 讓在此邏輯上線前已建立的核可供應商紀錄也套用同一規則。
 */
return new class extends Migration
{
    public function up(): void
    {
        $singleFacilityBySupplier = DB::table('supplier_facilities')
            ->select('supplier_id', DB::raw('COUNT(*) as cnt'), DB::raw('MIN(id) as facility_id'))
            ->groupBy('supplier_id')
            ->havingRaw('COUNT(*) = 1')
            ->get()
            ->keyBy('supplier_id');

        if ($singleFacilityBySupplier->isEmpty()) {
            return;
        }

        $rows = DB::table('material_item_suppliers')
            ->whereNull('supplier_facility_id')
            ->whereIn('supplier_id', $singleFacilityBySupplier->keys())
            ->get(['id', 'supplier_id']);

        foreach ($rows as $row) {
            $facilityId = $singleFacilityBySupplier[$row->supplier_id]->facility_id;
            DB::table('material_item_suppliers')
                ->where('id', $row->id)
                ->update(['supplier_facility_id' => $facilityId, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // 不可逆：無法區分哪些是這次回填、哪些是使用者手動選定，down() 不做還原
    }
};
