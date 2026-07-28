<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL 無法直接 ALTER enum 新增值時保留舊值，需重建欄位定義
        DB::statement("ALTER TABLE supplier_facilities MODIFY facility_type ENUM('manufacturing','warehouse','office','other','weaving','knitting','dyeing','printing','wet_processing','garment_assembly') NOT NULL DEFAULT 'manufacturing'");

        Schema::table('trade_good_suppliers', function (Blueprint $table) {
            $table->foreignUuid('supplier_facility_id')->nullable()->after('supplier_id')
                ->constrained('supplier_facilities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trade_good_suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_facility_id');
        });

        DB::statement("ALTER TABLE supplier_facilities MODIFY facility_type ENUM('manufacturing','warehouse','office','other') NOT NULL DEFAULT 'manufacturing'");
    }
};
