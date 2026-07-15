<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuestionTagSeeder extends Seeder
{
    private array $tags = [
        // ── ESG — E 環境 ────────────────────────────────────────────
        ['l1' => 'ESG', 'l2' => 'E-環境', 'l3' => 'GHG（溫室氣體）',          'slug' => 'esg.e.ghg',                   'label_en' => 'GHG Emissions',                    'engine' => 'ghg_scoring_v1',        'sort' => 10],
        ['l1' => 'ESG', 'l2' => 'E-環境', 'l3' => 'Water（水資源）',           'slug' => 'esg.e.water',                  'label_en' => 'Water Management',                 'engine' => 'water_scoring_v1',      'sort' => 20],
        ['l1' => 'ESG', 'l2' => 'E-環境', 'l3' => 'Energy（能源）',            'slug' => 'esg.e.energy',                 'label_en' => 'Energy Consumption',               'engine' => 'energy_scoring_v1',     'sort' => 30],
        ['l1' => 'ESG', 'l2' => 'E-環境', 'l3' => 'Waste（廢棄物）',           'slug' => 'esg.e.waste',                  'label_en' => 'Waste Management',                 'engine' => 'waste_scoring_v1',      'sort' => 40],
        ['l1' => 'ESG', 'l2' => 'E-環境', 'l3' => 'Biodiversity（生物多樣性）', 'slug' => 'esg.e.biodiversity',          'label_en' => 'Biodiversity',                     'engine' => null,                    'sort' => 50],
        // ESG — S 社會
        ['l1' => 'ESG', 'l2' => 'S-社會', 'l3' => 'Forced_Labor（強迫勞動）',  'slug' => 'esg.s.forced_labor',           'label_en' => 'Forced Labor',                     'engine' => 'labor_risk_v1',         'sort' => 10],
        ['l1' => 'ESG', 'l2' => 'S-社會', 'l3' => 'OHS（職業安全）',           'slug' => 'esg.s.ohs',                    'label_en' => 'Occupational Health & Safety',     'engine' => 'ohs_scoring_v1',        'sort' => 20],
        ['l1' => 'ESG', 'l2' => 'S-社會', 'l3' => 'DEIB（多元共融）',          'slug' => 'esg.s.deib',                   'label_en' => 'Diversity, Equity & Inclusion',    'engine' => null,                    'sort' => 30],
        ['l1' => 'ESG', 'l2' => 'S-社會', 'l3' => 'Community（社區）',         'slug' => 'esg.s.community',              'label_en' => 'Community Engagement',             'engine' => null,                    'sort' => 40],
        ['l1' => 'ESG', 'l2' => 'S-社會', 'l3' => 'ChildLabor（童工）',        'slug' => 'esg.s.child_labor',            'label_en' => 'Child Labor',                      'engine' => 'labor_risk_v1',         'sort' => 50],
        // ESG — G 治理
        ['l1' => 'ESG', 'l2' => 'G-治理', 'l3' => 'Anti_Corruption（反腐敗）', 'slug' => 'esg.g.anti_corruption',        'label_en' => 'Anti-Corruption',                  'engine' => 'governance_scoring_v1', 'sort' => 10],
        ['l1' => 'ESG', 'l2' => 'G-治理', 'l3' => 'DataPrivacy（資料隱私）',   'slug' => 'esg.g.data_privacy',           'label_en' => 'Data Privacy',                     'engine' => null,                    'sort' => 20],
        ['l1' => 'ESG', 'l2' => 'G-治理', 'l3' => 'Board（董事會）',           'slug' => 'esg.g.board',                  'label_en' => 'Board & Governance',               'engine' => 'governance_scoring_v1', 'sort' => 30],
        ['l1' => 'ESG', 'l2' => 'G-治理', 'l3' => 'Compliance（法遵）',        'slug' => 'esg.g.compliance',             'label_en' => 'Regulatory Compliance',            'engine' => null,                    'sort' => 40],
        ['l1' => 'ESG', 'l2' => 'G-治理', 'l3' => 'SupplyChain（供應鏈透明度）','slug' => 'esg.g.supply_chain',          'label_en' => 'Supply Chain Transparency',        'engine' => null,                    'sort' => 50],

        // ── ISO20400 — 七大主題（各 2 個 L3）────────────────────────
        ['l1' => 'ISO20400', 'l2' => '組織治理', 'l3' => '採購政策與承諾',  'slug' => 'iso20400.org_gov.policy',              'label_en' => 'Procurement Policy & Commitment',  'engine' => 'iso_scoring_v1', 'sort' => 10],
        ['l1' => 'ISO20400', 'l2' => '組織治理', 'l3' => '管理系統與稽核',  'slug' => 'iso20400.org_gov.audit',               'label_en' => 'Management System & Audit',        'engine' => 'iso_scoring_v1', 'sort' => 20],
        ['l1' => 'ISO20400', 'l2' => '人權',     'l3' => '強迫勞動防制',    'slug' => 'iso20400.human_rights.forced_labor',   'label_en' => 'Forced Labor Prevention',          'engine' => 'iso_scoring_v1', 'sort' => 10],
        ['l1' => 'ISO20400', 'l2' => '人權',     'l3' => '結社自由',        'slug' => 'iso20400.human_rights.freedom_of_association', 'label_en' => 'Freedom of Association', 'engine' => 'iso_scoring_v1', 'sort' => 20],
        ['l1' => 'ISO20400', 'l2' => '勞工',     'l3' => '薪資與工時',      'slug' => 'iso20400.labor.wages_hours',           'label_en' => 'Wages & Working Hours',            'engine' => 'iso_scoring_v1', 'sort' => 10],
        ['l1' => 'ISO20400', 'l2' => '勞工',     'l3' => '職業安全衛生',    'slug' => 'iso20400.labor.ohs',                   'label_en' => 'Occupational Health & Safety',     'engine' => 'iso_scoring_v1', 'sort' => 20],
        ['l1' => 'ISO20400', 'l2' => '環境',     'l3' => '溫室氣體排放',    'slug' => 'iso20400.environment.ghg',             'label_en' => 'GHG Emissions',                    'engine' => 'iso_scoring_v1', 'sort' => 10],
        ['l1' => 'ISO20400', 'l2' => '環境',     'l3' => '廢棄物與污染',    'slug' => 'iso20400.environment.waste',           'label_en' => 'Waste & Pollution',                'engine' => 'iso_scoring_v1', 'sort' => 20],
        ['l1' => 'ISO20400', 'l2' => '公平營運', 'l3' => '反腐敗與賄賂',    'slug' => 'iso20400.fair_ops.anti_bribery',       'label_en' => 'Anti-Bribery & Corruption',        'engine' => 'iso_scoring_v1', 'sort' => 10],
        ['l1' => 'ISO20400', 'l2' => '公平營運', 'l3' => '公平競爭',        'slug' => 'iso20400.fair_ops.competition',         'label_en' => 'Fair Competition',                 'engine' => 'iso_scoring_v1', 'sort' => 20],
        ['l1' => 'ISO20400', 'l2' => '消費者',   'l3' => '產品安全',        'slug' => 'iso20400.consumer.product_safety',     'label_en' => 'Product Safety',                   'engine' => 'iso_scoring_v1', 'sort' => 10],
        ['l1' => 'ISO20400', 'l2' => '消費者',   'l3' => '資訊揭露',        'slug' => 'iso20400.consumer.disclosure',         'label_en' => 'Consumer Information Disclosure',  'engine' => 'iso_scoring_v1', 'sort' => 20],
        ['l1' => 'ISO20400', 'l2' => '社區',     'l3' => '地方採購',        'slug' => 'iso20400.community.local_procurement', 'label_en' => 'Local Procurement',                'engine' => 'iso_scoring_v1', 'sort' => 10],
        ['l1' => 'ISO20400', 'l2' => '社區',     'l3' => '社會影響評估',    'slug' => 'iso20400.community.impact',            'label_en' => 'Social Impact Assessment',         'engine' => 'iso_scoring_v1', 'sort' => 20],

        // ── Geo-Risk ─────────────────────────────────────────────────
        ['l1' => 'Geo-Risk', 'l2' => '政治/制裁', 'l3' => '制裁名單審查',    'slug' => 'geo_risk.political.sanctions',         'label_en' => 'Sanctions Screening',              'engine' => null, 'sort' => 10],
        ['l1' => 'Geo-Risk', 'l2' => '政治/制裁', 'l3' => '政治風險評級',    'slug' => 'geo_risk.political.risk',              'label_en' => 'Political Risk Rating',            'engine' => null, 'sort' => 20],
        ['l1' => 'Geo-Risk', 'l2' => '物流/天災', 'l3' => '自然災害暴露度',  'slug' => 'geo_risk.logistics.natural_disaster',  'label_en' => 'Natural Disaster Exposure',        'engine' => null, 'sort' => 10],
        ['l1' => 'Geo-Risk', 'l2' => '物流/天災', 'l3' => '供應鏈中斷風險',  'slug' => 'geo_risk.logistics.disruption',        'label_en' => 'Supply Chain Disruption Risk',     'engine' => null, 'sort' => 20],
        ['l1' => 'Geo-Risk', 'l2' => '勞動/人權', 'l3' => '現代奴役風險',    'slug' => 'geo_risk.labor.modern_slavery',        'label_en' => 'Modern Slavery Risk',              'engine' => null, 'sort' => 10],
        ['l1' => 'Geo-Risk', 'l2' => '勞動/人權', 'l3' => '童工風險',        'slug' => 'geo_risk.labor.child_labor',           'label_en' => 'Child Labor Risk',                 'engine' => null, 'sort' => 20],

        // ── Product-Compliance ────────────────────────────────────────
        ['l1' => 'Product-Compliance', 'l2' => '化學品法規', 'l3' => 'RoHS（有害物質限制）',  'slug' => 'product_compliance.chemical.rohs',       'label_en' => 'RoHS Compliance',                  'engine' => null, 'sort' => 10],
        ['l1' => 'Product-Compliance', 'l2' => '化學品法規', 'l3' => 'REACH（化學品登記）',   'slug' => 'product_compliance.chemical.reach',      'label_en' => 'REACH Regulation',                 'engine' => null, 'sort' => 20],
        ['l1' => 'Product-Compliance', 'l2' => '化學品法規', 'l3' => '衝突礦物（CMRT）',      'slug' => 'product_compliance.chemical.conflict_minerals', 'label_en' => 'Conflict Minerals (CMRT)',   'engine' => null, 'sort' => 30],
        ['l1' => 'Product-Compliance', 'l2' => '貿易合規',   'l3' => 'CBAM（碳邊境調整機制）','slug' => 'product_compliance.trade.cbam',           'label_en' => 'Carbon Border Adjustment Mechanism','engine' => null, 'sort' => 10],
        ['l1' => 'Product-Compliance', 'l2' => '貿易合規',   'l3' => '出口管制',              'slug' => 'product_compliance.trade.export_control', 'label_en' => 'Export Control',                   'engine' => null, 'sort' => 20],
        ['l1' => 'Product-Compliance', 'l2' => '貿易合規',   'l3' => '原產地規則',            'slug' => 'product_compliance.trade.rules_of_origin','label_en' => 'Rules of Origin',                  'engine' => null, 'sort' => 30],
        ['l1' => 'Product-Compliance', 'l2' => '產品安全',   'l3' => 'CE / UL 認證',          'slug' => 'product_compliance.safety.certification', 'label_en' => 'CE / UL Certification',            'engine' => null, 'sort' => 10],
        ['l1' => 'Product-Compliance', 'l2' => '產品安全',   'l3' => '品質管理系統（ISO 9001）','slug' => 'product_compliance.safety.qms',          'label_en' => 'Quality Management System',        'engine' => null, 'sort' => 20],
    ];

    public function run(): void
    {
        foreach ($this->tags as $tag) {
            if (DB::table('question_tags')->where('slug', $tag['slug'])->exists()) {
                continue;
            }
            DB::table('question_tags')->insert([
                'id'                 => Str::uuid(),
                'l1_domain'          => $tag['l1'],
                'l2_pillar'          => $tag['l2'],
                'l3_topic'           => $tag['l3'],
                'slug'               => $tag['slug'],
                'label_zh'           => $tag['l3'],
                'label_en'           => $tag['label_en'],
                'scoring_engine_key' => $tag['engine'],
                'sort_order'         => $tag['sort'],
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }
    }
}
