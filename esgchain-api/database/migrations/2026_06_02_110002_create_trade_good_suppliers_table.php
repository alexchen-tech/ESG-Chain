<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_good_suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trade_good_id')->index();
            $table->uuid('supplier_id')->index();
            $table->uuid('material_group_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('trade_good_id')->references('id')->on('trade_goods')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->foreign('material_group_id')->references('id')->on('material_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_good_suppliers');
    }
};
