<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_goods', function (Blueprint $table) {
            $table->uuid('customer_id')->nullable()->after('id')->index();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });

        // NULL 值不參與 UNIQUE 比較（MySQL 標準行為），允許多筆 NULL
        Schema::table('trade_goods', function (Blueprint $table) {
            $table->unique(['customer_id', 'product_code'], 'trade_goods_customer_product_unique');
        });
    }

    public function down(): void
    {
        Schema::table('trade_goods', function (Blueprint $table) {
            $table->dropUnique('trade_goods_customer_product_unique');
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
