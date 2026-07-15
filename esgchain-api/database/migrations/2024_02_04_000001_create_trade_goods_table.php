<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // CBAM 適用的 HS Code 前綴（鋼鐵/水泥/鋁/化肥/電力/氫）
    public const CBAM_HS_PREFIXES = ['72', '73', '25', '76', '28', '31', '27'];

    public function up(): void
    {
        Schema::create('trade_goods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id')->index();
            $table->string('name');
            $table->string('hs_code', 10);
            $table->string('description')->nullable();
            $table->string('unit')->nullable()->comment('kg, ton, piece...');
            $table->decimal('quantity', 15, 4)->nullable();
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_cbam_applicable')->default(false);
            $table->string('cbam_category')->nullable()->comment('steel, cement, aluminium, fertiliser, electricity, hydrogen');
            $table->decimal('embedded_emissions', 15, 4)->nullable()->comment('kgCO2e per unit');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_goods');
    }
};
