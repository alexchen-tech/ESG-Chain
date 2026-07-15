<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_good_supplier_emissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trade_good_id')->index();
            $table->uuid('supplier_id')->index();
            $table->decimal('emissions_value', 15, 4)->comment('kgCO2e per unit');
            $table->text('calculation_note')->nullable();
            $table->timestamp('reported_at')->useCurrent();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->foreign('trade_good_id')->references('id')->on('trade_goods')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_good_supplier_emissions');
    }
};
