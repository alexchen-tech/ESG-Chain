<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pcf_snapshots', function (Blueprint $table) {
            $table->renameColumn('buyer_product_id', 'sales_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('pcf_snapshots', function (Blueprint $table) {
            $table->renameColumn('sales_product_id', 'buyer_product_id');
        });
    }
};
