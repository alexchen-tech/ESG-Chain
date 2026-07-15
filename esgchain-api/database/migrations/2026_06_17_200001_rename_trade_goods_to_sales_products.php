<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('trade_goods', 'sales_products');

        Schema::table('sales_products', function (Blueprint $table) {
            $table->json('applicable_regulations')->nullable()->after('embedded_emissions');
            $table->json('inferred_regulations')->nullable()->after('applicable_regulations');
        });
    }

    public function down(): void
    {
        Schema::table('sales_products', function (Blueprint $table) {
            $table->dropColumn(['applicable_regulations', 'inferred_regulations']);
        });

        Schema::rename('sales_products', 'trade_goods');
    }
};
