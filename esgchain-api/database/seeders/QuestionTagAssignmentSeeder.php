<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuestionTagAssignmentSeeder extends Seeder
{
    /**
     * 關鍵字 → tag slugs 映射表
     * key: 出現在 question_text 或 tags 欄位的關鍵字（中英文）
     * value: 對應的 question_tags.slug 陣列
     */
    private const KEYWORD_MAP = [
        // ── 環境 ──
        '溫室氣體'         => ['esg.env.ghg_emission', 'esg.sc_env.scope3'],
        'GHG'              => ['esg.env.ghg_emission', 'esg.sc_env.scope3'],
        'scope'            => ['esg.env.ghg_emission', 'esg.sc_env.scope3'],
        '碳排'             => ['esg.env.ghg_emission', 'esg.env.carbon_neutrality'],
        '碳中和'           => ['esg.env.carbon_neutrality'],
        '碳足跡'           => ['esg.env.ghg_emission', 'esg.env.carbon_neutrality'],
        '能源'             => ['esg.env.energy_consumption'],
        '再生能源'         => ['esg.env.energy_consumption', 'esg.env.carbon_neutrality'],
        '用水'             => ['esg.env.water_usage'],
        '水資源'           => ['esg.env.water_usage'],
        '廢棄物'           => ['esg.env.waste_management', 'iso26k.env.pollution'],
        '廢水'             => ['esg.env.water_usage', 'iso26k.env.pollution'],
        '生物多樣'         => ['esg.env.biodiversity', 'iso26k.env.biodiversity'],
        '化學品'           => ['esg.env.chemical_mgmt', 'prod_comp.chem.sds'],
        '化學合規'         => ['esg.env.chemical_mgmt', 'prod_comp.chem.reach', 'prod_comp.chem.rohs'],
        'REACH'            => ['prod_comp.chem.reach', 'esg.env.chemical_mgmt'],
        'RoHS'             => ['prod_comp.chem.rohs', 'esg.env.chemical_mgmt'],
        'SDS'              => ['prod_comp.chem.sds'],
        '包裝'             => ['esg.sc_env.packaging'],
        '循環材料'         => ['esg.sc_env.packaging'],
        '原料溯源'         => ['esg.sc_env.raw_material', 'prod_comp.eudr.traceability'],
        '永續採購'         => ['iso20400.policy.commitment', 'iso20400.policy.criteria'],
        '採購培訓'         => ['iso20400.policy.supplier_dev', 'esg.gov.supplier_code'],
        '責任採購'         => ['iso20400.policy.commitment', 'iso20400.policy.criteria'],
        '採購決策'         => ['iso20400.policy.criteria'],
        '採購規格'         => ['iso20400.policy.criteria'],

        // ── EUDR / 森林 ──
        'EUDR'             => ['esg.sc_env.eudr', 'prod_comp.eudr.dds', 'prod_comp.eudr.traceability', 'prod_comp.eudr.certification'],
        '森林'             => ['esg.sc_env.eudr', 'prod_comp.eudr.certification'],
        'FSC'              => ['prod_comp.eudr.certification'],
        'DDS'              => ['prod_comp.eudr.dds'],

        // ── 強迫勞動 / 勞工 ──
        '強迫勞動'         => ['esg.labor.forced_labor', 'iso26k.hr.sc_risk'],
        'UFLPA'            => ['prod_comp.trace.uflpa', 'esg.labor.forced_labor'],
        '童工'             => ['esg.labor.child_labor'],
        '工時'             => ['esg.labor.working_hours'],
        '工資'             => ['esg.labor.wages'],
        '薪酬'             => ['esg.labor.wages'],
        '結社'             => ['esg.labor.freedom_assoc', 'iso26k.labor.social_dialogue'],
        '集體談判'         => ['esg.labor.freedom_assoc', 'iso26k.labor.social_dialogue'],
        '歧視'             => ['esg.labor.discrimination'],
        '平等'             => ['esg.labor.discrimination'],
        '就業關係'         => ['iso26k.labor.employment_rel'],
        '勞動條件'         => ['iso26k.labor.conditions'],
        '人力培訓'         => ['iso26k.labor.development'],
        '職涯'             => ['iso26k.labor.development'],

        // ── 職安 ──
        '職安'             => ['esg.ohs.safety_mgmt', 'iso26k.labor.ohs'],
        'OSH'              => ['esg.ohs.safety_mgmt', 'iso26k.labor.ohs'],
        'ISO 45001'        => ['esg.ohs.safety_mgmt'],
        '安全管理'         => ['esg.ohs.safety_mgmt'],
        '事故'             => ['esg.ohs.incident_rate'],
        'PPE'              => ['esg.ohs.ppe'],
        '防護裝備'         => ['esg.ohs.ppe'],
        '安全教育'         => ['esg.ohs.training'],
        '安全訓練'         => ['esg.ohs.training'],

        // ── 人權 ──
        '人權'             => ['iso26k.hr.due_diligence', 'iso26k.hr.sc_risk'],
        '盡職調查'         => ['iso26k.hr.due_diligence', 'iso20400.risk.due_diligence'],
        '申訴'             => ['iso26k.hr.grievance'],
        '救濟'             => ['iso26k.hr.grievance'],
        '脆弱群體'         => ['iso26k.hr.vulnerable'],
        '移工'             => ['iso26k.hr.vulnerable', 'esg.labor.forced_labor'],
        '供應鏈人權'       => ['iso26k.hr.sc_risk'],

        // ── 治理 ──
        '行為準則'         => ['esg.gov.code_of_conduct'],
        '承諾書'           => ['esg.gov.code_of_conduct', 'iso20400.policy.commitment'],
        '反腐'             => ['esg.gov.anti_corruption', 'iso26k.fair.anti_corruption'],
        '反賄'             => ['esg.gov.anti_corruption', 'iso26k.fair.anti_corruption'],
        '吹哨'             => ['esg.gov.whistleblower'],
        '揭弊'             => ['esg.gov.whistleblower'],
        '透明'             => ['esg.gov.transparency', 'iso26k.gov.disclosure'],
        '資訊揭露'         => ['esg.gov.transparency', 'iso26k.gov.disclosure'],
        '董事'             => ['esg.gov.board'],
        '供應商行為準則'   => ['esg.gov.supplier_code'],
        '課責'             => ['iso26k.gov.accountability'],
        '利害關係人'       => ['iso26k.gov.stakeholder'],

        // ── ISO 20400 ──
        '永續風險'         => ['iso20400.risk.assessment'],
        '風險評估'         => ['iso20400.risk.assessment', 'geo_risk.geopo.sanctions'],
        'KPI'              => ['iso20400.perf.kpi'],
        '稽核'             => ['iso20400.perf.audit'],
        'ESG稽核'          => ['iso20400.perf.audit'],
        '無預警稽核'       => ['iso20400.perf.audit'],
        '供應商稽核'       => ['iso20400.perf.audit'],
        '永續報告'         => ['iso20400.perf.report'],
        '國家風險'         => ['iso20400.risk.country', 'geo_risk.geopo.conflict'],

        // ── 地緣政治 / 供應鏈韌性 ──
        '制裁'             => ['geo_risk.geopo.sanctions'],
        '出口管制'         => ['geo_risk.geopo.sanctions'],
        '技術管制'         => ['geo_risk.geopo.sanctions'],
        '衝突礦物'         => ['geo_risk.geopo.conflict', 'prod_comp.trace.cmrt'],
        'CMRT'             => ['prod_comp.trace.cmrt'],
        '關稅'             => ['geo_risk.geopo.tariff'],
        '貿易壁壘'         => ['geo_risk.geopo.tariff'],
        '政治風險'         => ['geo_risk.geopo.conflict', 'geo_risk.geopo.sanctions'],
        '天災'             => ['geo_risk.logistics.disaster'],
        '自然災害'         => ['geo_risk.logistics.disaster'],
        '氣候風險'         => ['geo_risk.logistics.disaster', 'iso26k.env.climate'],
        'BCP'              => ['geo_risk.logistics.bcp'],
        '業務持續'         => ['geo_risk.logistics.bcp'],
        '應急預案'         => ['geo_risk.logistics.bcp'],
        '雙軌採購'         => ['geo_risk.logistics.single_src'],
        'Dual Sourcing'    => ['geo_risk.logistics.single_src'],
        '單一來源'         => ['geo_risk.logistics.single_src'],
        '可追溯性'         => ['prod_comp.eudr.traceability', 'esg.sc_env.raw_material'],

        // ── CBAM ──
        'CBAM'             => ['prod_comp.cbam.embedded_emission', 'prod_comp.cbam.reporting', 'prod_comp.cbam.product_coverage'],
        '內含碳'           => ['prod_comp.cbam.embedded_emission'],

        // ── 溯源 / 原產地 ──
        '原產地'           => ['prod_comp.trace.origin_cert'],
        '棉花'             => ['prod_comp.trace.uflpa', 'esg.sc_env.raw_material'],

        // ── 社區 / 消費者 ──
        '社區'             => ['esg.comm.community_invest', 'iso26k.comm.involvement'],
        '社會影響'         => ['iso26k.comm.sia'],
        '產品安全'         => ['esg.comm.product_safety', 'iso26k.consumer.safety'],
        '資料隱私'         => ['esg.comm.data_privacy', 'iso26k.consumer.data_privacy'],
        '資安'             => ['esg.comm.data_privacy'],
        '網路安全'         => ['esg.comm.data_privacy'],
        '就業創造'         => ['iso26k.comm.employment'],
        '在地文化'         => ['iso26k.comm.culture'],
        '公平競爭'         => ['iso26k.fair.competition'],
        '智慧財產'         => ['iso26k.fair.property_rights'],
        '供應鏈社會責任'   => ['iso26k.fair.sc_promotion'],
        '永續消費'         => ['iso26k.consumer.sustainable'],
        '公平行銷'         => ['iso26k.consumer.marketing'],

        // ── 實體安全（Geo-Risk / 物流）──
        '實體安全'         => ['geo_risk.logistics.bcp'],
        '貨物管控'         => ['geo_risk.logistics.bcp'],
        '場地安全'         => ['geo_risk.logistics.bcp'],
        '人員管控'         => ['geo_risk.logistics.bcp'],
        '背景查核'         => ['iso26k.hr.due_diligence'],
        '身份識別'         => ['iso26k.hr.due_diligence'],
        'GPS追蹤'          => ['geo_risk.logistics.bcp'],
        '物流安全'         => ['geo_risk.logistics.bcp'],
        '環境衝擊'         => ['iso26k.env.pollution', 'iso26k.env.precaution'],
        '治理'             => ['esg.gov.code_of_conduct', 'iso26k.gov.accountability'],
        '污染'             => ['iso26k.env.pollution'],
        '預防原則'         => ['iso26k.env.precaution'],
        '資源'             => ['iso26k.env.resource'],
        '氣候'             => ['iso26k.env.climate', 'esg.env.ghg_emission'],
    ];

    public function run(): void
    {
        // 取所有 tags
        $tagsBySlug = DB::table('question_tags')
            ->pluck('id', 'slug')
            ->toArray();

        // 取所有題目
        $questions = DB::table('saq_questions')
            ->select('id', 'question_text', 'tags')
            ->get();

        $insertCount = 0;

        foreach ($questions as $question) {
            // 先清除舊的 assignments
            DB::table('question_tag_assignments')
                ->where('question_id', $question->id)
                ->delete();

            $freeTags = json_decode($question->tags ?? '[]', true) ?: [];
            $haystack = $question->question_text . ' ' . implode(' ', $freeTags);

            $matchedSlugs = [];

            foreach (self::KEYWORD_MAP as $keyword => $slugs) {
                if (mb_stripos($haystack, $keyword) !== false) {
                    foreach ($slugs as $slug) {
                        $matchedSlugs[$slug] = true;
                    }
                }
            }

            // 若完全沒匹配到，依 free tag 推斷 l1_domain 兜底
            if (empty($matchedSlugs)) {
                $matchedSlugs = $this->fallbackByFreeTag($freeTags, $tagsBySlug);
            }

            // 寫入 assignments
            foreach (array_keys($matchedSlugs) as $slug) {
                if (!isset($tagsBySlug[$slug])) continue;
                DB::table('question_tag_assignments')->insertOrIgnore([
                    'question_id' => $question->id,
                    'tag_id'      => $tagsBySlug[$slug],
                ]);
                $insertCount++;
            }
        }

        $this->command->info("Done: {$questions->count()} questions processed, {$insertCount} tag assignments created.");
    }

    /**
     * 完全無關鍵字命中時，用 free tags 文字猜測最接近的 l1_domain
     */
    private function fallbackByFreeTag(array $freeTags, array $tagsBySlug): array
    {
        $text = implode(' ', $freeTags);
        $matched = [];

        $domainGuess = [
            '環境' => 'esg.env.ghg_emission',
            '勞工' => 'esg.labor.forced_labor',
            '治理' => 'esg.gov.code_of_conduct',
            '社會' => 'iso26k.labor.conditions',
            '採購' => 'iso20400.policy.commitment',
            '風險' => 'iso20400.risk.assessment',
            '合規' => 'prod_comp.chem.reach',
        ];

        foreach ($domainGuess as $kw => $slug) {
            if (mb_stripos($text, $kw) !== false && isset($tagsBySlug[$slug])) {
                $matched[$slug] = true;
            }
        }

        // 最終兜底：esg.gov.code_of_conduct
        if (empty($matched) && isset($tagsBySlug['esg.gov.code_of_conduct'])) {
            $matched['esg.gov.code_of_conduct'] = true;
        }

        return $matched;
    }
}
