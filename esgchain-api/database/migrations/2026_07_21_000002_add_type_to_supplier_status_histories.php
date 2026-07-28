<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // CLAUDE.md 要求 ERP status 轉換與 ESG-Chain onboarding_stage 轉換要有各自獨立的稽核軌跡，
    // 但 Supplier::transitionStatus() / transitionOnboardingStage() 一直共用同一張表、沒有欄位區分，
    // 時間軸 UI 無法分辨哪筆是哪種轉換。補上 type 欄位；既有資料全數回填為 onboarding
    // （目前唯一有實際 UI 呼叫的是 transitionOnboarding，ERP transition() 端點從未被前端呼叫過）。
    public function up(): void
    {
        Schema::table('supplier_status_histories', function (Blueprint $table) {
            $table->enum('type', ['erp_status', 'onboarding'])->default('onboarding')->after('supplier_id');
        });

        DB::table('supplier_status_histories')->update(['type' => 'onboarding']);
    }

    public function down(): void
    {
        Schema::table('supplier_status_histories', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
