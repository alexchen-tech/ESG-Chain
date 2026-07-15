<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * S³P 供應鏈 60 題全維度深度評核 — 5 份問卷範本
 *
 * 範本清單：
 * 1. S³P 全維度深度評核（60 題）— 涵蓋四大框架
 * 2. S³P 社會責任評核（ISO 26000，15 題）
 * 3. S³P 供應鏈安全管理評核（ISO 28000，15 題）
 * 4. S³P 永續採購評核（ISO 20400，15 題）
 * 5. S³P 地緣風險韌性評核（GPR，15 題）
 */
class SaqS3PTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $allQuestions = $this->allQuestions();

        // ── Step 1：建立 60 道題庫題目（template_id = null → 出現於題目庫）──
        $bankIds = [];
        foreach ($allQuestions as $i => $q) {
            // 若已存在，直接取 id 以避免重複
            $existing = DB::table('saq_questions')
                ->whereNull('template_id')
                ->where('question_text', $q['text'])
                ->value('id');

            if ($existing) {
                // 確保既有記錄的 compliance_domains 已清空（合規範疇欄位僅用於物料合規，非 ESG 框架）
                DB::table('saq_questions')->where('id', $existing)->update(['compliance_domains' => json_encode([])]);
                $bankIds[] = $existing;
                continue;
            }

            $bankId = (string) Str::uuid();
            DB::table('saq_questions')->insert([
                'id'                      => $bankId,
                'template_id'             => null,
                'question_text'           => $q['text'],
                'question_type'           => $q['type'],
                'options'                 => isset($q['options']) ? json_encode($q['options']) : null,
                'weight'                  => $q['weight'],
                'order'                   => $i + 1,
                'is_required'             => 1,
                'sasb_topic_id'           => null,
                'sasb_metric_code'        => null,
                'tags'                    => isset($q['tags']) ? json_encode($q['tags']) : null,
                'compliance_domains'      => json_encode($q['domains']),
                'source_bank_question_id' => null,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
            $bankIds[] = $bankId;
        }
        $this->command->info('✓ 題庫：共 ' . count($bankIds) . ' 道題目已建立（template_id = null）');

        // ── Step 1.5：先指派題庫標籤，trg_saq_questions_framework_check trigger
        //    會在 Step 2 INSERT 範本題目時檢查 source_bank_question_id 是否已有符合框架的 TAG ──
        $this->assignBankQuestionTags($allQuestions, $bankIds);

        // ── Step 2：建立 5 份範本，每題以 source_bank_question_id 關聯題庫 ──
        $templates = [
            [
                'name'        => 'S³P 全維度深度評核（60 題）',
                'version'     => '2025.1',
                'status'      => 'published',
                'scoring_framework' => 'ESG',
                'description' => '整合 ISO 26000 / ISO 28000 / ISO 20400 三大 ISO 框架與地緣政治韌性（GPR）之完整 60 題評核，適用於關鍵 Tier-1 供應商年度全面盤查。',
                'bankIdxs'    => range(0, 59),
            ],
            [
                'name'        => 'S³P 社會責任評核（ISO 26000）',
                'version'     => '2025.1',
                'status'      => 'published',
                'scoring_framework' => 'ISO26000',
                'description' => '依 ISO 26000 六大核心主題設計，15 題涵蓋組織治理、人權、勞工實踐、環境、公平運作、消費者及社區發展，適用於初始 ESG 基線評估。',
                'bankIdxs'    => range(0, 14),
            ],
            [
                'name'        => 'S³P 供應鏈安全管理評核（ISO 28000）',
                'version'     => '2025.1',
                'status'      => 'published',
                'scoring_framework' => 'ISO28000',
                'description' => '以 ISO 28000 供應鏈安全管理系統為核心，15 題評估實體安全、人員管控、貨物封條、資通安全與業務持續能力，適用於關鍵物流節點稽核。',
                'bankIdxs'    => range(15, 29),
            ],
            [
                'name'        => 'S³P 永續採購評核（ISO 20400）',
                'version'     => '2025.1',
                'status'      => 'published',
                'scoring_framework' => 'ISO20400',
                'description' => '對應 ISO 20400 永續採購指引，15 題評核採購政策、生命週期成本邏輯、碳排盤點（Scope 3）、CAP 矯正機制及供應商在地化比例。',
                'bankIdxs'    => range(30, 44),
            ],
            [
                'name'        => 'S³P 地緣風險韌性評核（GPR）',
                'version'     => '2025.1',
                'status'      => 'published',
                'scoring_framework' => 'Geo-Risk',
                'description' => '聚焦地緣政治風險（GPR）與供應鏈韌性，15 題評估雙軌採購、制裁篩查（OFAC/EU）、War Gaming 演練與危機決策授權，適用於高風險地區廠商。',
                'bankIdxs'    => range(45, 59),
            ],
        ];

        foreach ($templates as $tplData) {
            $bankIdxs = $tplData['bankIdxs'];
            unset($tplData['bankIdxs']);

            // 避免重複（但補上 scoring_framework，因舊版 Seeder 未設定此欄位）
            $existingId = DB::table('saq_templates')->where('name', $tplData['name'])->value('id');
            if ($existingId) {
                DB::table('saq_templates')->where('id', $existingId)->update([
                    'scoring_framework' => $tplData['scoring_framework'],
                ]);
                $this->command->line("已存在，補上 scoring_framework：{$tplData['name']}");
                continue;
            }

            $templateId = (string) Str::uuid();
            DB::table('saq_templates')->insert(array_merge($tplData, [
                'id'         => $templateId,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            foreach ($bankIdxs as $order => $bankIdx) {
                $q          = $allQuestions[$bankIdx];
                $bankQuesId = $bankIds[$bankIdx];

                DB::table('saq_questions')->insert([
                    'id'                      => (string) Str::uuid(),
                    'template_id'             => $templateId,
                    'question_text'           => $q['text'],
                    'question_type'           => $q['type'],
                    'options'                 => isset($q['options']) ? json_encode($q['options']) : null,
                    'weight'                  => $q['weight'],
                    'order'                   => $order + 1,
                    'is_required'             => 1,
                    'sasb_topic_id'           => null,
                    'sasb_metric_code'        => null,
                    'tags'                    => isset($q['tags']) ? json_encode($q['tags']) : null,
                    'compliance_domains'      => json_encode([]),
                    'source_bank_question_id' => $bankQuesId,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ]);
            }

            $this->command->info("✓ 建立範本：{$tplData['name']}（" . count($bankIdxs) . " 題）");
        }
    }

    /**
     * 將題庫題目依其 domains[0] 指派至對應 l1_domain 的 question_tags，
     * 補齊 question_tag_assignments（缺少的 l1_domain tag 視需要建立 General 佔位 tag）
     */
    private function assignBankQuestionTags(array $allQuestions, array $bankIds): void
    {
        $domainToL1 = [
            'ISO26000' => 'ISO26000',
            'ISO28000' => 'ISO28000',
            'ISO20400' => 'ISO20400',
            'GPR'      => 'Geo-Risk',
            'CE'       => 'ESG',
        ];

        $tagIdCache = [];
        $assigned   = 0;

        $getOrCreateTag = function (string $l1) use (&$tagIdCache) {
            if (isset($tagIdCache[$l1])) return $tagIdCache[$l1];
            $tagId = DB::table('question_tags')->where('l1_domain', $l1)->value('id');
            if (!$tagId) {
                $tagId = (string) Str::uuid();
                DB::table('question_tags')->insert([
                    'id'         => $tagId,
                    'l1_domain'  => $l1,
                    'l2_pillar'  => 'General',
                    'l3_topic'   => 'General',
                    'slug'       => Str::slug($l1) . '.general',
                    'label_zh'   => $l1 . ' 通用',
                    'label_en'   => $l1 . ' General',
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            return $tagIdCache[$l1] = $tagId;
        };

        $assignTag = function (string $bankQuesId, string $tagId) use (&$assigned) {
            $exists = DB::table('question_tag_assignments')
                ->where('question_id', $bankQuesId)
                ->where('tag_id', $tagId)
                ->exists();
            if (!$exists) {
                DB::table('question_tag_assignments')->insert([
                    'question_id' => $bankQuesId,
                    'tag_id'      => $tagId,
                ]);
                $assigned++;
            }
        };

        $esgTagId = $getOrCreateTag('ESG');

        foreach ($allQuestions as $i => $q) {
            $bankQuesId = $bankIds[$i];

            // 每題皆屬於「全維度深度評核（60 題）」範本（scoring_framework=ESG），故統一加掛 ESG tag
            $assignTag($bankQuesId, $esgTagId);

            $domain = $q['domains'][0] ?? null;
            $l1     = $domainToL1[$domain] ?? null;
            if (!$l1 || $l1 === 'ESG') continue;

            $assignTag($bankQuesId, $getOrCreateTag($l1));
        }

        $this->command->info("✓ 題庫標籤指派：新增 {$assigned} 筆 question_tag_assignments");
    }

    // ─────────────────────────────────────────────────────────────────────
    // 60 題完整題庫
    // domains: ISO26000 | ISO28000 | ISO20400 | GPR
    // type: boolean（是/否）| single_choice（有選項）
    // weight: 依各題在框架中的關鍵程度 0.01–0.05
    // ─────────────────────────────────────────────────────────────────────

    private function allQuestions(): array
    {
        // 是/否 選項（boolean 題共用）
        $yn = ['是', '否'];

        return [
            // ══════════════════════════════════════════════════════════════
            // Section I：社會責任與組織治理（ISO 26000）Q1–Q15
            // ══════════════════════════════════════════════════════════════
            [
                'text'    => '是否出具最高管理階層簽署之社會責任承諾書？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['治理', '承諾書'],
                'domains' => ['ISO26000'],
            ],
            [
                'text'    => '是否建立正式的外部利益關係人溝通機制？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.02,
                'tags'    => ['利益相關方', '溝通'],
                'domains' => ['ISO26000'],
            ],
            [
                'text'    => '組織是否出具透明的決策程序與年度治理報告？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['透明度', '治理報告'],
                'domains' => ['ISO26000'],
            ],
            [
                'text'    => '是否嚴格禁止任何形式的童工與強迫勞動？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.05,
                'tags'    => ['人權', '童工', '強迫勞動'],
                'domains' => ['ISO26000'],
            ],
            [
                'text'    => '是否出具防範工作場所騷擾與歧視的正式政策？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['勞工', '反歧視'],
                'domains' => ['ISO26000'],
            ],
            [
                'text'    => '是否保障員工團體協商參與和加入工會的基本權利？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.02,
                'tags'    => ['勞工', '工會'],
                'domains' => ['ISO26000'],
            ],
            [
                'text'    => '是否提供符合或優於當地法律的薪資福利？',
                'type'    => 'single_choice',
                'options' => ['符合最低薪資', '高於最低薪資 10% 以內', '高於最低薪資 10%–30%', '高於最低薪資 30% 以上'],
                'weight'  => 0.03,
                'tags'    => ['薪資', '勞工'],
                'domains' => ['ISO26000'],
            ],
            [
                'text'    => '廠房是否出具完整的職業安全衛生管理系統（OSH）？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['安全衛生', 'OSH'],
                'domains' => ['ISO26000'],
            ],
            [
                'text'    => '是否對員工進行定期的專業技能培訓與職涯發展規劃？',
                'type'    => 'single_choice',
                'options' => ['未實施', '不定期辦理', '每年至少一次', '每季辦理並有記錄'],
                'weight'  => 0.02,
                'tags'    => ['培訓', '人才發展'],
                'domains' => ['ISO26000'],
            ],
            [
                'text'    => '是否已出行環境影響評估並制定能源減耗基準？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['環境', '能源'],
                'domains' => ['ISO26000'],
            ],
            [
                'text'    => '是否針對生產過程中的廢棄物與化學品嚴格管控？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['廢棄物', '化學品'],
                'domains' => ['ISO26000'],
            ],
            [
                'text'    => '是否出具明確的反貪腐、反賄賂與公平競爭準則？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['反貪腐', '公平競爭'],
                'domains' => ['ISO26000'],
            ],
            [
                'text'    => '是否保護並促進消費者隱私權與個人資料安全？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.02,
                'tags'    => ['隱私', '資料安全'],
                'domains' => ['ISO26000'],
            ],
            [
                'text'    => '是否出具客戶服務申訴機制並追蹤改善成效？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.02,
                'tags'    => ['客訴', '消費者保護'],
                'domains' => ['ISO26000'],
            ],
            [
                'text'    => '組織是否參與在地社區發展並建立實質貢獻記錄？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.02,
                'tags'    => ['社區', '社會貢獻'],
                'domains' => ['ISO26000'],
            ],

            // ══════════════════════════════════════════════════════════════
            // Section II：供應鏈安全管理系統（ISO 28000）Q16–Q30
            // ══════════════════════════════════════════════════════════════
            [
                'text'    => '廠房是否已取得 ISO 28000 或 C-TPAT/AEO 安全認證？',
                'type'    => 'single_choice',
                'options' => ['未取得任何認證', 'C-TPAT 認證', 'AEO 認證', 'ISO 28000 認證', '多項認證並存'],
                'weight'  => 0.05,
                'tags'    => ['ISO28000', '認證'],
                'domains' => ['ISO28000'],
            ],
            [
                'text'    => '實體在廠是否出具高度安全性（含圍籬、燈光、感測器）？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['實體安全'],
                'domains' => ['ISO28000'],
            ],
            [
                'text'    => '關鍵設施的出入口是否設有電子閘門及 24H 監視系統？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['門禁', '監視'],
                'domains' => ['ISO28000'],
            ],
            [
                'text'    => '是否對所有員工、訪客與外包商實施嚴格的身份識別？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['人員管控', '身份識別'],
                'domains' => ['ISO28000'],
            ],
            [
                'text'    => '新進人員與外商是否出行完整的背景查核程序？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['背景查核', '人員安全'],
                'domains' => ['ISO28000'],
            ],
            [
                'text'    => '裝卸貨物區是否限制人員進出並實施場地管控？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['貨物管控', '場地安全'],
                'domains' => ['ISO28000'],
            ],
            [
                'text'    => '貨物封條管理是否符合 ISO 17712 標準並出具記錄？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['封條', 'ISO17712'],
                'domains' => ['ISO28000'],
            ],
            [
                'text'    => '運輸車輛是否出具即時 GPS 追蹤與超速警報系統？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['GPS追蹤', '物流安全'],
                'domains' => ['ISO28000'],
            ],
            [
                'text'    => '是否對物流供應商進行年度安全績效評估？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['物流評估', '安全績效'],
                'domains' => ['ISO28000'],
            ],
            [
                'text'    => '內部網路是否出具防火牆、IDS/IPS 與定期弱點掃描？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.05,
                'tags'    => ['資安', '網路安全'],
                'domains' => ['ISO28000'],
            ],
            [
                'text'    => '資料中心是否出具備援系統與異地備份機制？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['資安', '資料備份'],
                'domains' => ['ISO28000'],
            ],
            [
                'text'    => '是否制定正式的資訊安全事件通報與應變程序？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['資安事件', '應變'],
                'domains' => ['ISO28000'],
            ],
            [
                'text'    => '是否出具業務持續與緊急應變計畫（EPRP）並每年演練？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['BCP', 'EPRP', '緊急應變'],
                'domains' => ['ISO28000'],
            ],
            [
                'text'    => '是否對安全管理流程進行定期內部稽核（Internal Audit）？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['內部稽核'],
                'domains' => ['ISO28000'],
            ],
            [
                'text'    => '高層主管是否每年參與安全管理審查（Management Review）？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.02,
                'tags'    => ['管理審查', '高層承諾'],
                'domains' => ['ISO28000'],
            ],

            // ══════════════════════════════════════════════════════════════
            // Section III：永續採購實作（ISO 20400）Q31–Q45
            // ══════════════════════════════════════════════════════════════
            [
                'text'    => '組織是否出具正式的永續採購政策與策略文件？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['永續採購', '政策'],
                'domains' => ['ISO20400'],
            ],
            [
                'text'    => '是否將 ESG 指標納入供應商合約之法律條款中？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['ESG合約', '採購條款'],
                'domains' => ['ISO20400'],
            ],
            [
                'text'    => '是否定義採購前置之永續優先順序（Prioritizing Issues）？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['採購優先序', '永續採購'],
                'domains' => ['ISO20400'],
            ],
            [
                'text'    => '是否出具「生命週期成本（LCC）」之採購評估邏輯？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['LCC', '生命週期'],
                'domains' => ['ISO20400'],
            ],
            [
                'text'    => '是否對採購人員進行永續與責任採購之專業培訓？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['採購培訓', '責任採購'],
                'domains' => ['ISO20400'],
            ],
            [
                'text'    => '是否對關鍵一階供應商出行年度 ESG 實地現場稽核？',
                'type'    => 'single_choice',
                'options' => ['未進行', '不定期實施', '每 2 年一次', '每年一次', '每年 + 不定期抽查'],
                'weight'  => 0.04,
                'tags'    => ['ESG稽核', '供應商稽核'],
                'domains' => ['ISO20400'],
            ],
            [
                'text'    => '是否出具「供應商矯正行動（CAP）」跟進與轉化機制？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['CAP', '矯正行動'],
                'domains' => ['ISO20400'],
            ],
            [
                'text'    => '是否追蹤並公開供應商在地採購比例（Local Sourcing）？',
                'type'    => 'single_choice',
                'options' => ['未追蹤', '內部追蹤未公開', '公開揭露但無目標', '公開揭露並設有改善目標'],
                'weight'  => 0.02,
                'tags'    => ['在地採購'],
                'domains' => ['ISO20400'],
            ],
            [
                'text'    => '是否出具追溯產品原料來源至原產地之可追溯性系統？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['可追溯性', '原料溯源'],
                'domains' => ['ISO20400'],
            ],
            [
                'text'    => '是否要求供應商使用環保可回收包裝材料？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.02,
                'tags'    => ['包裝', '循環材料'],
                'domains' => ['ISO20400'],
            ],
            [
                'text'    => '是否針對供應鏈碳排（Scope 3）進行盤點與逐步減排？',
                'type'    => 'single_choice',
                'options' => ['未啟動', '正在進行盤點', '已完成盤點', '已完成盤點並設定減排目標', '已完成盤點並有年度進展報告'],
                'weight'  => 0.05,
                'tags'    => ['Scope3', '碳排', '減碳'],
                'domains' => ['ISO20400'],
            ],
            [
                'text'    => '是否定期舉辦供應商永續大會並分享標竿案例與政策？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['供應商大會', '標竿學習'],
                'domains' => ['ISO20400'],
            ],
            [
                'text'    => '組織是否鼓勵供應商採用再生能源進行生產？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['再生能源', '綠色生產'],
                'domains' => ['ISO20400'],
            ],
            [
                'text'    => '採購規格中是否包含對有害化學物質（RoHS/REACH）之禁令？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['RoHS', 'REACH', '化學合規'],
                'domains' => ['ISO20400', 'CE'],
            ],
            [
                'text'    => '是否衡量並改善採購決策對環境造成的長期潛在衝擊？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['環境衝擊', '採購決策'],
                'domains' => ['ISO20400'],
            ],

            // ══════════════════════════════════════════════════════════════
            // Section IV：地緣風險與韌性（GPR）Q46–Q60
            // ══════════════════════════════════════════════════════════════
            [
                'text'    => '是否出具「地緣政治風險監控機制」並對高風險廠商標記？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.05,
                'tags'    => ['地緣政治', '風險監控'],
                'domains' => ['GPR'],
            ],
            [
                'text'    => '是否對關鍵物料出具「複數採購來源（Dual Sourcing）」分散策略？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.05,
                'tags'    => ['雙軌採購', 'Dual Sourcing'],
                'domains' => ['GPR'],
            ],
            [
                'text'    => '核心零件量產是否分佈於不同的地緣政治板塊？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['地緣分散', '韌性'],
                'domains' => ['GPR'],
            ],
            [
                'text'    => '是否對位於新興技術衝突凸顯產地之供應商出行無預警調查？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['無預警稽核', '技術管制'],
                'domains' => ['GPR'],
            ],
            [
                'text'    => '針對「邊境關閉」或「貿易制裁」是否出具替代物流方案？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['替代物流', '制裁應對'],
                'domains' => ['GPR'],
            ],
            [
                'text'    => '是否出具因「能源可生能源中斷」之備電與應急計畫？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['能源韌性', '備電'],
                'domains' => ['GPR', 'ISO28000'],
            ],
            [
                'text'    => '關鍵供應商是否出具對當地政治不穩定的應急預案？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['政治風險', '應急預案'],
                'domains' => ['GPR'],
            ],
            [
                'text'    => '是否針對高風險地區供應商建立「緊急通訊矩陣」？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['緊急通訊', '危機管理'],
                'domains' => ['GPR'],
            ],
            [
                'text'    => '是否定期進行「地緣政治危機模擬演練（War Gaming）」？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['War Gaming', '危機演練'],
                'domains' => ['GPR'],
            ],
            [
                'text'    => '組織是否出具即時解析「制裁措施名單（OFAC/EU）」的能力？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['OFAC', '制裁篩查'],
                'domains' => ['GPR'],
            ],
            [
                'text'    => '是否參考政府機構和國際組織取得第三方情資資訊？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['情資', '地緣情報'],
                'domains' => ['GPR'],
            ],
            [
                'text'    => '針對高度依賴之單一國家採購源，是否制定年度減壓計畫？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.04,
                'tags'    => ['集中度', '供應商多元化'],
                'domains' => ['GPR'],
            ],
            [
                'text'    => '是否評估跨國性水資源或極端氣候對供應鏈路徑的影響？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['氣候風險', '水資源'],
                'domains' => ['GPR'],
            ],
            [
                'text'    => '是否針對供應鏈中關鍵技術與智慧財產地區保護做完整布局？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.03,
                'tags'    => ['智財保護', '技術安全'],
                'domains' => ['GPR'],
            ],
            [
                'text'    => '組織高層是否出具面對地緣政治引發之大規模中斷的決策權？',
                'type'    => 'boolean',
                'options' => $yn,
                'weight'  => 0.05,
                'tags'    => ['決策授權', '危機指揮'],
                'domains' => ['GPR'],
            ],
        ];
    }
}
