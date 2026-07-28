<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dpp_category 是 DPP（歐盟數位產品護照）類別判定，跟 cbam_category（CBAM 六類）
 * 是兩套不同的分類體系，不可混用。目前只實作 battery 一種值，其餘類別留 null，
 * 待未來擴充其他 DPP 類別時再擴充判定邏輯。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_products', function (Blueprint $table) {
            $table->string('dpp_category', 30)->nullable()->after('cbam_category');
        });
    }

    public function down(): void
    {
        Schema::table('sales_products', function (Blueprint $table) {
            $table->dropColumn('dpp_category');
        });
    }
};
