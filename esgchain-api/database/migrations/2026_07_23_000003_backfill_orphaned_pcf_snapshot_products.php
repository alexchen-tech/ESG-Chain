<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * pcf_snapshots.sales_product_id 有 40 筆歷史孤兒資料：對應的 buyer_products 在
 * 2026_06_17_200005 遷移時已被軟刪除（deleted_at 不為 null），因此未被建立對應的
 * sales_products 記錄；buyer_products 資料表本身已於後續遷移完全移除，已無法還原
 * 這些快照原本屬於哪個產品。
 *
 * PcfSnapshot 依專案規範為 append-only，不可刪除舊版本（CLAUDE.md）。此處改為替
 * 這些孤兒 sales_product_id 補建立最小、立即軟刪除的佔位 sales_products 記錄，
 * 誠實標示「產品已刪除、快照保留供稽核」，恢復參照完整性但不偽造成真實／可見產品。
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphanIds = DB::table('pcf_snapshots as ps')
            ->leftJoin('sales_products as sp', 'sp.id', '=', 'ps.sales_product_id')
            ->whereNull('sp.id')
            ->distinct()
            ->pluck('ps.sales_product_id');

        if ($orphanIds->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($orphanIds as $id) {
            DB::table('sales_products')->insert([
                'id'          => $id,
                'name'        => '已刪除產品（原始資料已隨遷移移除，僅保留 PCF 快照供稽核）',
                'hs_code'     => '0000000000',
                'currency'    => 'USD',
                'created_at'  => $now,
                'updated_at'  => $now,
                'deleted_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        // 不可逆：無法區分此遷移建立的佔位記錄與真實資料，down() 不做任何事。
    }
};
