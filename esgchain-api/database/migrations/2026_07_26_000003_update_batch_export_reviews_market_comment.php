<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 純文件層級修正：market 欄位 comment 原本寫死 EU/US/UK/JP/GLOBAL，
 * 跟現在的權威市場代碼清單（App\Models\MarketComplianceRule::MARKETS）對不上，
 * 不改資料、只更新 comment 避免誤導後續看 schema 的人。用原生 SQL 而非
 * Schema::table()->change()，因為專案未安裝 doctrine/dbal。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE batch_export_reviews MODIFY market VARCHAR(10) NOT NULL COMMENT '目標市場代碼，見 MarketComplianceRule::MARKETS（EU/US/UK/JP/APAC/NA）'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE batch_export_reviews MODIFY market VARCHAR(10) NOT NULL COMMENT '目標市場代碼（EU/US/UK/JP/GLOBAL）'");
    }
};
