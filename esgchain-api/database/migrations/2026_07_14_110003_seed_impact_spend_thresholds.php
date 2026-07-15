<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // spend 因子固定門檻（可調）：spend_amount ≥ 門檻 → 對應子分數。
        // ⚠️ 預設值需上線前依實際採購額分布校準。
        DB::table('system_settings')->upsert([
            [
                'key'        => 'impact_spend_thresholds',
                'value'      => json_encode(['s5' => 10000000, 's4' => 3000000, 's3' => 1000000, 's2' => 300000]),
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['key'], ['value', 'updated_at']);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'impact_spend_thresholds')->delete();
    }
};
