<?php

namespace Database\Seeders;

use App\Models\QuestionTag;
use App\Models\QuestionTagAssignment;
use App\Models\SAQQuestion;
use App\Models\SAQTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Multi-framework 混合範本 Seed
 *
 * 建立一個 scoring_framework = "multi-framework" 的範本，
 * 題目同時掛 iso26k.* 和 iso20400.* slug，供計分引擎同時輸出三軸分。
 */
class MultiFrameworkTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // 若已存在則跳過
        if (SAQTemplate::where('scoring_framework', 'multi-framework')->exists()) {
            $this->command->info('Multi-framework 範本已存在，跳過。');
            return;
        }

        $template = SAQTemplate::create([
            'id'                => Str::uuid(),
            'name'              => '永續供應商綜合評核問卷 v1（Multi-framework）',
            'version'           => 1,
            'description'       => 'ISO 26000 社會責任 + ISO 20400 永續採購雙框架混合問卷。一次填答同時輸出 ESG 揭露（軸1）與治理成熟度（軸2）風險分。',
            'status'            => 'published',
            'is_active'         => true,
            'scoring_framework' => 'multi-framework',
            'created_by_id'     => null,
        ]);

        // 定義題目清單：每題帶多框架 slugs
        $questions = [
            // ── 人權 / 勞工（iso26k.hr + iso26k.labor）────────────────────
            [
                'text'    => '貴公司是否有書面的人權政策？',
                'type'    => 'boolean',
                'weight'  => 1.0,
                'slugs'   => ['iso26k.hr.policy', 'iso20400.policy.written_policy'],
                'order'   => 1,
            ],
            [
                'text'    => '過去一年，貴公司是否有童工或強迫勞動事件？',
                'type'    => 'boolean',
                'weight'  => 1.5,
                'slugs'   => ['iso26k.hr.child_labor'],
                'order'   => 2,
                'dir'     => 'negative',
            ],
            [
                'text'    => '貴公司是否建立勞工申訴機制？',
                'type'    => 'boolean',
                'weight'  => 1.0,
                'slugs'   => ['iso26k.labor.grievance', 'iso20400.risk.due_diligence'],
                'order'   => 3,
            ],
            [
                'text'    => '貴公司勞工薪資是否符合當地法定最低薪資？',
                'type'    => 'single_choice',
                'options' => ['完全符合', '大部分符合', '部分符合', '少部分符合', '不符合'],
                'weight'  => 1.0,
                'slugs'   => ['iso26k.labor.wages'],
                'order'   => 4,
            ],
            // ── 環境（iso26k.env）────────────────────────────────────────
            [
                'text'    => '貴公司是否有溫室氣體排放盤查（如 ISO 14064 或 GHG Protocol）？',
                'type'    => 'boolean',
                'weight'  => 1.2,
                'slugs'   => ['iso26k.env.ghg_inventory'],
                'order'   => 5,
            ],
            [
                'text'    => '貴公司過去一年是否有重大環境違規記錄？',
                'type'    => 'boolean',
                'weight'  => 1.5,
                'slugs'   => ['iso26k.env.violations'],
                'order'   => 6,
                'dir'     => 'negative',
            ],
            // ── 治理（iso26k.gov + iso20400.policy）─────────────────────
            [
                'text'    => '貴公司是否有反腐敗政策並定期培訓員工？',
                'type'    => 'boolean',
                'weight'  => 1.0,
                'slugs'   => ['iso26k.gov.anti_corruption', 'iso20400.policy.ethics'],
                'order'   => 7,
            ],
            [
                'text'    => '貴公司是否定期向利害關係人發布永續報告？',
                'type'    => 'boolean',
                'weight'  => 0.8,
                'slugs'   => ['iso26k.gov.transparency'],
                'order'   => 8,
            ],
            // ── 公平營運（iso26k.fair）────────────────────────────────────
            [
                'text'    => '貴公司是否要求下游供應商遵守行為準則？',
                'type'    => 'boolean',
                'weight'  => 1.0,
                'slugs'   => ['iso26k.fair.supply_chain_conduct', 'iso20400.risk.supplier_screening'],
                'order'   => 9,
            ],
            // ── ISO 20400 採購政策（iso20400.policy）─────────────────────
            [
                'text'    => '貴公司是否有書面的永續採購政策？',
                'type'    => 'boolean',
                'weight'  => 1.0,
                'slugs'   => ['iso20400.policy.sustainable_procurement'],
                'order'   => 10,
            ],
            [
                'text'    => '貴公司是否將永續標準納入供應商評選條件？',
                'type'    => 'single_choice',
                'options' => ['完全符合', '大部分符合', '部分符合', '少部分符合', '不符合'],
                'weight'  => 1.2,
                'slugs'   => ['iso20400.perf.supplier_evaluation'],
                'order'   => 11,
            ],
            [
                'text'    => '貴公司是否對供應商進行永續風險評估？',
                'type'    => 'boolean',
                'weight'  => 1.0,
                'slugs'   => ['iso20400.risk.risk_assessment'],
                'order'   => 12,
            ],
        ];

        foreach ($questions as $q) {
            $question = SAQQuestion::create([
                'id'              => Str::uuid(),
                'template_id'     => $template->id,
                'question_text'   => $q['text'],
                'question_type'   => $q['type'],
                'options'         => $q['options'] ?? null,
                'weight'          => $q['weight'],
                'order'           => $q['order'],
                'is_required'     => true,
                'scoring_direction' => $q['dir'] ?? 'positive',
            ]);

            // 掛 tag slugs
            foreach ($q['slugs'] as $slug) {
                $tag = QuestionTag::where('slug', $slug)->first();
                if ($tag) {
                    QuestionTagAssignment::firstOrCreate([
                        'question_id' => $question->id,
                        'tag_id'      => $tag->id,
                    ]);
                }
            }
        }

        $this->command->info("✓ Multi-framework 範本已建立（{$template->id}），共 " . count($questions) . ' 題。');
    }
}
