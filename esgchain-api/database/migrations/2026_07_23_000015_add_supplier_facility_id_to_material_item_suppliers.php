<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 製程廠區欄位遷移到物料核可供應商清單：製程地點（哪個供應商的哪個廠做什麼製程）
 * 是「供應商＋物料」的固定屬性，不是「供應商＋特定產品」的屬性，不應留在
 * TradeGoodSupplier（該表 supplier_facility_id 欄位保留但不再是新資料寫入目標）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_item_suppliers', function (Blueprint $table) {
            $table->foreignUuid('supplier_facility_id')->nullable()->after('supplier_id')
                ->constrained('supplier_facilities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('material_item_suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_facility_id');
        });
    }
};
