<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_bom_lines', function (Blueprint $table) {
            // 改名 buyer_product_id → sales_product_id
            $table->renameColumn('buyer_product_id', 'sales_product_id');
        });

        Schema::table('product_bom_lines', function (Blueprint $table) {
            // 新增子產品 FK（型態 B BomLine）
            $table->uuid('child_sales_product_id')->nullable()->after('sales_product_id');
            $table->foreign('child_sales_product_id')
                  ->references('id')->on('sales_products')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_bom_lines', function (Blueprint $table) {
            $table->dropForeign(['child_sales_product_id']);
            $table->dropColumn('child_sales_product_id');
        });

        Schema::table('product_bom_lines', function (Blueprint $table) {
            $table->renameColumn('sales_product_id', 'buyer_product_id');
        });
    }
};
