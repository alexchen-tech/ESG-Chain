<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // 對外批次資料包 API 金鑰（X-Api-Key）；換發時更新此值即可
        DB::table('system_settings')->upsert([
            [
                'key'        => 'export_api_key',
                'value'      => 'esgchain-export-' . bin2hex(random_bytes(16)),
                'updated_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['key'], []); // 已存在則不覆蓋（保留既有金鑰）
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'export_api_key')->delete();
    }
};
