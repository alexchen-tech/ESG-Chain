<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trade_goods', function (Blueprint $table) {
            $table->enum('emissions_source', ['pcf_sync', 'supplier_reported', 'manual'])
                  ->nullable()
                  ->after('embedded_emissions')
                  ->comment('資料來源：pcf_sync=PCF自動同步, supplier_reported=供應商回報, manual=手動輸入');
            $table->timestamp('emissions_updated_at')
                  ->nullable()
                  ->after('emissions_source')
                  ->comment('碳排量最後更新時間');
        });
    }

    public function down(): void
    {
        Schema::table('trade_goods', function (Blueprint $table) {
            $table->dropColumn(['emissions_source', 'emissions_updated_at']);
        });
    }
};
