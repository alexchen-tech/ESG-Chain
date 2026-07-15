<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // trade_good_id 在 trade_goods 改名後已自動對應；若仍存在才改名
        if (Schema::hasColumn('shipment_lines', 'trade_good_id')) {
            Schema::table('shipment_lines', function (Blueprint $table) {
                $table->renameColumn('trade_good_id', 'sales_product_id');
            });
        }

        if (Schema::hasColumn('shipment_lines', 'buyer_product_id')) {
            Schema::table('shipment_lines', function (Blueprint $table) {
                $table->dropForeign(['buyer_product_id']);
                $table->dropColumn('buyer_product_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('shipment_lines', function (Blueprint $table) {
            $table->uuid('buyer_product_id')->nullable()->after('sales_product_id');
        });

        Schema::table('shipment_lines', function (Blueprint $table) {
            $table->renameColumn('sales_product_id', 'trade_good_id');
        });
    }
};
