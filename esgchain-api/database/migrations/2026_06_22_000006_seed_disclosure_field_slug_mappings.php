<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * bank question ID → disclosure_field_slug 映射。
     * 只映射有明確量化語意的題，長文字題不映射。
     */
    private array $mappings = [
        // numeric 題
        '4dcd7499-44cf-4229-8b4c-52ee12153f37' => 'ghg.scope1_mt_co2e',   // Scope 1 排放量
        'af9de116-6b9b-4dfe-937b-4b1f8919df2e' => 'energy.total_kwh',     // 總用電量
        '44655e5a-69a9-43cf-b3dd-38ef83180112' => 'water.total_m3',       // 總用水量
        '95fb2148-e053-4444-9468-66d6e02f9787' => 'safety.ltifr',         // LTIFR
        '7d17e497-6741-47ce-8166-dd386cb688e5' => 'diversity.female_pct', // 女性員工比例
        // boolean 題
        '315ed186-915b-4ce8-a29f-5ea53955604c' => 'governance.has_anti_corruption_policy', // 反貪腐準則
        '5b5cb44a-87c4-4ac5-9bcd-a4fa379fef9f' => 'labor.child_labor_banned',             // 禁止童工
        'f795d6ee-023e-47a0-8d25-183a18b35bc3' => 'supply_chain.supplier_audit_conducted', // 供應商盡職調查
    ];

    public function up(): void
    {
        foreach ($this->mappings as $questionId => $fieldSlug) {
            DB::table('saq_questions')
                ->where('id', $questionId)
                ->whereNull('disclosure_field_slug')
                ->update(['disclosure_field_slug' => $fieldSlug, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('saq_questions')
            ->whereIn('id', array_keys($this->mappings))
            ->update(['disclosure_field_slug' => null, 'updated_at' => now()]);
    }
};
