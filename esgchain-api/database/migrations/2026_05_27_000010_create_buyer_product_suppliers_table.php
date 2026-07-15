<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_product_suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('buyer_product_id')->index();
            $table->uuid('supplier_id')->index();
            $table->uuid('material_group_id')->nullable()->index();
            $table->timestamps();

            $table->foreign('buyer_product_id')->references('id')->on('buyer_products')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->foreign('material_group_id')->references('id')->on('material_groups')->nullOnDelete();

            $table->index(['buyer_product_id', 'supplier_id', 'material_group_id'], 'bps_composite_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_product_suppliers');
    }
};
