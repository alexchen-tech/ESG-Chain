<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 新增 onboarding_stage 欄位（先不改 status，避免資料遺失）
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('onboarding_stage')->nullable()->after('status')
                ->comment('內部入職階段：potential/invited/reviewing/certified');
        });

        // 2. 遷移現有 status → onboarding_stage
        DB::statement("
            UPDATE suppliers SET
                onboarding_stage = CASE status
                    WHEN 'potential'   THEN 'potential'
                    WHEN 'invited'     THEN 'invited'
                    WHEN 'reviewing'   THEN 'reviewing'
                    WHEN 'certified'   THEN 'certified'
                    WHEN 'suspended'   THEN 'certified'
                    WHEN 'terminated'  THEN 'certified'
                    ELSE 'potential'
                END,
                status = CASE status
                    WHEN 'potential'   THEN 'active'
                    WHEN 'invited'     THEN 'active'
                    WHEN 'reviewing'   THEN 'active'
                    WHEN 'certified'   THEN 'active'
                    WHEN 'suspended'   THEN 'suspended'
                    WHEN 'terminated'  THEN 'inactive'
                    ELSE 'active'
                END
        ");

        // 3. 修改 status 欄位為新 ENUM（資料已遷移後再改型態）
        DB::statement("ALTER TABLE suppliers MODIFY COLUMN status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE suppliers MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'active'");

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('onboarding_stage');
        });
    }
};
