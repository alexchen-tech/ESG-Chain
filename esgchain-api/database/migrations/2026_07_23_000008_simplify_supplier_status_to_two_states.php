<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 依 ERP 匯入一致性，供應商 status（ERP 擁有欄位）簡化為僅兩種狀態：
 * active（生效，資料匯入時預設值）／inactive（停用）。原本的 suspended
 * 從未在 ERP 匯入語意中使用（只有 ESG-Chain 自有的 onboarding_stage
 * 才有 suspended 這個中繼狀態），故先將既有 suspended 資料併入 inactive
 * 再收斂 ENUM 定義。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('suppliers')->where('status', 'suspended')->update(['status' => 'inactive']);
        DB::statement("ALTER TABLE suppliers MODIFY COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE suppliers MODIFY COLUMN status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active'");
    }
};
