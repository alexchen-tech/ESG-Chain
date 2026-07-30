<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedGhgScope3DisclosureField extends Migration
{
    public function up(): void
    {
        DB::table('supplier_disclosure_fields')->updateOrInsert(
            ['slug' => 'ghg.scope3_mt_co2e'],
            [
                'label' => '範疇三排放量（噸CO2e）',
                'data_type' => 'numeric',
                'unit' => 'mt_co2e',
                'period_type' => 'annual',
                'options' => null,
                'description' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('supplier_disclosure_fields')->where('slug', 'ghg.scope3_mt_co2e')->delete();
    }
}
