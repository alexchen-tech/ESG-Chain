<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 從既有 bom_line_suppliers（透過 product_bom_lines.material_item_id）回填
 * material_item_suppliers：同一物料若在多個產品的 BOM 都登記過同一供應商，
 * 這裡只保留一筆（去重），role 只要任一筆是 primary 就視為 primary。
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('bom_line_suppliers as bls')
            ->join('product_bom_lines as pbl', 'pbl.id', '=', 'bls.bom_line_id')
            ->whereNotNull('pbl.material_item_id')
            ->select('pbl.material_item_id', 'bls.supplier_id', 'bls.role', 'bls.source')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $key = $row->material_item_id . '|' . $row->supplier_id;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'material_item_id' => $row->material_item_id,
                    'supplier_id'      => $row->supplier_id,
                    'role'             => $row->role,
                    'source'           => $row->source,
                ];
            } elseif ($row->role === 'primary') {
                $grouped[$key]['role'] = 'primary';
            }
        }

        $now = now();
        foreach ($grouped as $data) {
            DB::table('material_item_suppliers')->insertOrIgnore([
                'id'               => (string) Str::orderedUuid(),
                'material_item_id' => $data['material_item_id'],
                'supplier_id'      => $data['supplier_id'],
                'role'             => $data['role'],
                'source'           => $data['source'],
                'sort_order'       => 0,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
    }

    public function down(): void
    {
        // 不可逆：回填資料為去重彙總，無法還原成逐筆對應關係
    }
};
