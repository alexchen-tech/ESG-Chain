<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 回填 material_item_suppliers 時發現：同一物料在不同產品的 BOM 各自被標記
 * primary 的供應商可能不同（例如同一顆拉鍊頭，A 產品指定供應商甲為主要，
 * B 產品指定供應商乙為主要）——這正是「BOM 物料固定但供應商不同」的實例。
 * 物料層級核可清單只允許一個 primary，回填當下用「該物料最早建立的那筆
 * primary 關聯」保留為 primary，其餘降為 alternate（不影響既有 bom_line_suppliers，
 * 各產品原本各自的 primary/alternate 設定完全不變）。
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('material_item_suppliers')
            ->where('role', 'primary')
            ->select('material_item_id')
            ->groupBy('material_item_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('material_item_id');

        foreach ($duplicates as $materialItemId) {
            $keepId = DB::table('material_item_suppliers')
                ->where('material_item_id', $materialItemId)
                ->where('role', 'primary')
                ->orderBy('created_at')
                ->value('id');

            DB::table('material_item_suppliers')
                ->where('material_item_id', $materialItemId)
                ->where('role', 'primary')
                ->where('id', '!=', $keepId)
                ->update(['role' => 'alternate']);
        }
    }

    public function down(): void
    {
        // 不可逆：降級的 alternate 已無法區分原本是否為某產品的 primary
    }
};
