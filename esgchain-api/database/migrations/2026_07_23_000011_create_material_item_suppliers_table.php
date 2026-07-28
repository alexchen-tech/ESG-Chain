<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 物料層級核可供應商清單：取代「每個產品的 BOM 行各自登記一次同一物料的供應商」，
 * 改成對每個物料只登記一次，所有使用這個物料的產品共用。
 *
 * 不影響、不取代既有 bom_line_suppliers（PCF 計算、風險評分、缺口掃描、ERP 同步等
 * 十餘處既有邏輯都依賴它以 bom_line 為單位運作，貿然替換風險過高）；
 * 此表是新的、額外的「核可清單」來源，之後透過「套用物料核可清單」動作把資料
 * 灌入特定產品的 bom_line_suppliers，兩者並存。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_item_suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('material_item_id')->constrained('material_items')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->enum('role', ['primary', 'alternate'])->default('primary');
            $table->enum('source', ['erp_designated', 'manual'])->default('manual');
            $table->tinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('material_item_id');
            $table->index('supplier_id');
            $table->unique(['material_item_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_item_suppliers');
    }
};
