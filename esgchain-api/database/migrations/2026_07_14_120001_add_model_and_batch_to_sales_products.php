<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_products', function (Blueprint $table) {
            // 型號（款式/Style No.，與 product_code SKU 區隔）
            $table->string('model_no', 100)->nullable()->after('product_code')
                ->comment('產品型號 / 款式編號');
            // 生產批號（代表性生產批次 Lot No.）
            $table->string('production_batch_no', 100)->nullable()->after('model_no')
                ->comment('生產批號 / Lot No.');
        });
    }

    public function down(): void
    {
        Schema::table('sales_products', function (Blueprint $table) {
            $table->dropColumn(['model_no', 'production_batch_no']);
        });
    }
};
