<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->upsert([
            [
                'key'        => 'dim_weight_defaults',
                'value'      => json_encode(['E1'=>0.25,'E2'=>0.15,'E3'=>0.20,'E4'=>0.15,'E5'=>0.10,'E6'=>0.15]),
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['key'], ['value', 'updated_at']);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'dim_weight_defaults')->delete();
    }
};
