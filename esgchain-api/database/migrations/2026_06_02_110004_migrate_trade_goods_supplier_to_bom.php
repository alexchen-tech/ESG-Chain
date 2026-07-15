<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('trade_goods')
            ->whereNotNull('supplier_id')
            ->select('id', 'supplier_id', 'material_group_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('trade_good_suppliers')->insert([
                'id'                => Str::uuid()->toString(),
                'trade_good_id'     => $row->id,
                'supplier_id'       => $row->supplier_id,
                'material_group_id' => $row->material_group_id,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }

    public function down(): void
    {
        // 遷移回填無法安全復原，down() 僅清空遷移記錄
        DB::table('trade_good_suppliers')->delete();
    }
};
