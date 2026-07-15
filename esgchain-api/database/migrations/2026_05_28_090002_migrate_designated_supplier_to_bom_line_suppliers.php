<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $lines = DB::table('product_bom_lines')
            ->whereNotNull('designated_supplier_id')
            ->select('id', 'designated_supplier_id')
            ->get();

        $now = now();
        foreach ($lines as $line) {
            DB::table('bom_line_suppliers')->insertOrIgnore([
                'id'          => (string) Str::uuid(),
                'bom_line_id' => $line->id,
                'supplier_id' => $line->designated_supplier_id,
                'role'        => 'primary',
                'source'      => 'erp_designated',
                'sort_order'  => 0,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Restore designated_supplier_id from bom_line_suppliers primary records
        $primaries = DB::table('bom_line_suppliers')
            ->where('role', 'primary')
            ->select('bom_line_id', 'supplier_id')
            ->get();

        foreach ($primaries as $record) {
            DB::table('product_bom_lines')
                ->where('id', $record->bom_line_id)
                ->update(['designated_supplier_id' => $record->supplier_id]);
        }
    }
};
