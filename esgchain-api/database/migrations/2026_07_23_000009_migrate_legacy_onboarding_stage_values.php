<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 現有供應商資料的 onboarding_stage 欄位仍殘留舊版六值詞彙
 * （potential/invited/reviewing/certified/suspended/terminated，來自
 * 2024_02_01 建表時的舊 status 欄位），未隨後續 2024_03_01 的三值狀態機
 * （active/suspended/terminated，見 CLAUDE.md「供應商狀態機」）遷移。
 *
 * 前端 statusLabel()/ONBOARDING_TRANSITIONS 只認得三值詞彙，導致這些供應商
 * 的入選階段一律無法辨識可用轉換、標籤退回顯示原始英文字串。這裡把
 * potential/invited/reviewing/certified 一律視為已正式運作中的供應商，
 * 收斂為 active；suspended/terminated 語意不變、原樣保留。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('suppliers')
            ->whereIn('onboarding_stage', ['potential', 'invited', 'reviewing', 'certified'])
            ->update(['onboarding_stage' => 'active']);
    }

    public function down(): void
    {
        // 不可逆：舊六值詞彙的原始分類已無法還原
    }
};
