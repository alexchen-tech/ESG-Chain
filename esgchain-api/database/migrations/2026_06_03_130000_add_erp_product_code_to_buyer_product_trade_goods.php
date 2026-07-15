<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('buyer_product_trade_goods', function (Blueprint $table) {
            $table->string('erp_product_code', 100)
                  ->nullable()
                  ->after('note')
                  ->comment('ERP 料號，供 Phase 2 Webhook 匹配用');
        });
    }

    public function down(): void
    {
        Schema::table('buyer_product_trade_goods', function (Blueprint $table) {
            $table->dropColumn('erp_product_code');
        });
    }
};
