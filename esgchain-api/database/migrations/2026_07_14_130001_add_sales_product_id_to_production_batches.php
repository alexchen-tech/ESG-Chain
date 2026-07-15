<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_batches', function (Blueprint $table) {
            // 生產批次所屬的成衣（一對多：一款成衣有多個生產批次）
            $table->uuid('sales_product_id')->nullable()->after('supplier_id')->index()
                ->comment('所屬銷售產品（成衣）');
        });
    }

    public function down(): void
    {
        Schema::table('production_batches', function (Blueprint $table) {
            $table->dropColumn('sales_product_id');
        });
    }
};
