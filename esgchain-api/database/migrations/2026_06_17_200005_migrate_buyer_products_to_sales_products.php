<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // 修正 product_bom_lines FK：改指向 sales_products（migration 200002 改欄位名但未更新 FK 目標）
        $fks = collect(DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_bom_lines'
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'"))->pluck('CONSTRAINT_NAME')->toArray();
        Schema::table('product_bom_lines', function (\Illuminate\Database\Schema\Blueprint $table) use ($fks) {
            if (in_array('product_bom_lines_buyer_product_id_foreign', $fks)) {
                $table->dropForeign('product_bom_lines_buyer_product_id_foreign');
            }
            if (!in_array('product_bom_lines_sales_product_id_foreign', $fks)) {
                $table->foreign('sales_product_id')->references('id')->on('sales_products')->cascadeOnDelete();
            }
        });

        // 1. BuyerProduct 有 finished_good 連結者 → 把 ESG 欄位合併到對應 SalesProduct
        $finishedLinks = DB::table('buyer_product_trade_goods')
            ->where('relation_type', 'finished_good')
            ->get();

        foreach ($finishedLinks as $link) {
            $bp = DB::table('buyer_products')->find($link->buyer_product_id);
            if (!$bp) continue;

            DB::table('sales_products')
                ->where('id', $link->trade_good_id)
                ->update([
                    'applicable_regulations' => $bp->applicable_regulations,
                    'inferred_regulations'   => $bp->inferred_regulations,
                ]);

            // BomLine 重新指向 SalesProduct
            DB::table('product_bom_lines')
                ->where('sales_product_id', $bp->id)
                ->update(['sales_product_id' => $link->trade_good_id]);

            // PcfSnapshot 重新指向 SalesProduct
            DB::table('pcf_snapshots')
                ->where('sales_product_id', $bp->id)
                ->update(['sales_product_id' => $link->trade_good_id]);
        }

        // 2. BuyerProduct 無 finished_good 連結者 → 在 sales_products 建立新記錄
        $linkedIds = DB::table('buyer_product_trade_goods')
            ->where('relation_type', 'finished_good')
            ->pluck('buyer_product_id');

        $unlinked = DB::table('buyer_products')
            ->whereNotIn('id', $linkedIds)
            ->whereNull('deleted_at')
            ->get();

        foreach ($unlinked as $bp) {
            $newId = $bp->id; // 沿用相同 UUID，簡化 BomLine/PCF 重指
            $exists = DB::table('sales_products')->where('id', $newId)->exists();
            if (!$exists) {
                DB::table('sales_products')->insert([
                    'id'                     => $newId,
                    'name'                   => $bp->name,
                    'product_code'           => $bp->product_code ?? '',
                    'hs_code'                => '',
                    'description'            => $bp->description,
                    'applicable_regulations' => $bp->applicable_regulations,
                    'inferred_regulations'   => $bp->inferred_regulations,
                    'created_at'             => $bp->created_at,
                    'updated_at'             => $bp->updated_at,
                ]);
            }
            // BomLine / PcfSnapshot UUID 已對應，無需更新
        }

        // 3. component 關係 → BomLine 型態 B
        $componentLinks = DB::table('buyer_product_trade_goods')
            ->where('relation_type', 'component')
            ->get();

        foreach ($componentLinks as $link) {
            // 找到 BuyerProduct 對應的 SalesProduct id
            $finishedLink = DB::table('buyer_product_trade_goods')
                ->where('buyer_product_id', $link->buyer_product_id)
                ->where('relation_type', 'finished_good')
                ->first();

            $parentSalesProductId = $finishedLink
                ? $finishedLink->trade_good_id
                : $link->buyer_product_id; // fallback: unlinked 沿用原 UUID

            // 避免重複建立
            $exists = DB::table('product_bom_lines')
                ->where('sales_product_id', $parentSalesProductId)
                ->where('child_sales_product_id', $link->trade_good_id)
                ->exists();

            if (!$exists) {
                DB::table('product_bom_lines')->insert([
                    'id'                      => \Illuminate\Support\Str::uuid()->toString(),
                    'sales_product_id'        => $parentSalesProductId,
                    'child_sales_product_id'  => $link->trade_good_id,
                    'material_item_id'        => null,
                    'material_name'           => '',
                    'bom_line_type'           => 'material',
                    'quantity'               => null,
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]);
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // 逆向遷移僅刪除型態 B BomLine（結構遷移由各別 migration down() 處理）
        DB::table('product_bom_lines')
            ->whereNotNull('child_sales_product_id')
            ->delete();
    }
};
