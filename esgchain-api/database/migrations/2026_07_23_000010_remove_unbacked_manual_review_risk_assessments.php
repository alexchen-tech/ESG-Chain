<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 系統採問卷驅動立場：風險評估只能由 SAQ 完成後自動推導，`RiskAssessmentController::store()`
 * 已明文禁止手動建立（見該方法內 abort(403) 訊息）。但先前某個 demo seeder 繞過此限制，
 * 直接對 4 家完全沒有問卷紀錄的供應商（皆為 Tier 4 原料商）以 DB::table()->insert()
 * 建立 source_type='manual_review' 的風險評估，導致「風險歷史」分頁在無問卷紀錄時仍顯示
 * 六維度評分與 AI 建議，違反「無問卷紀錄，就不會有風險歷史」的既定規則。
 *
 * 清除所有供應商完全無已評分 SAQ、卻存在 risk_assessments 紀錄的資料（目前僅
 * source_type='manual_review' 這 4 筆符合）。
 */
return new class extends Migration
{
    public function up(): void
    {
        $unbackedIds = DB::table('risk_assessments as ra')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('saqs')
                    ->whereColumn('saqs.supplier_id', 'ra.supplier_id')
                    ->whereNotNull('saqs.score');
            })
            ->pluck('ra.id');

        DB::table('risk_assessments')->whereIn('id', $unbackedIds)->delete();
    }

    public function down(): void
    {
        // 不可逆：刪除的是違反問卷驅動規則的資料，無需也不應復原
    }
};
