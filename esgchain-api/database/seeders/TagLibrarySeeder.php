<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TagLibrarySeeder extends Seeder
{
    public function run(): void
    {
        // 採 upsert by slug（而非 truncate），避免清空既有 question_tag_assignments
        // 與已建立的 saq_questions 關聯（slug 為唯一鍵且建立後不可變）
        $tags  = $this->tags();
        $order = 0;
        $created = 0;
        $updated = 0;

        foreach ($tags as $entry) {
            $existing = DB::table('question_tags')->where('slug', $entry['slug'])->first();

            if ($existing) {
                DB::table('question_tags')->where('id', $existing->id)->update([
                    'l1_domain'          => $entry['l1'],
                    'l2_pillar'          => $entry['l2'],
                    'l3_topic'           => $entry['label_zh'],
                    'label_zh'           => $entry['label_zh'],
                    'label_en'           => $entry['label_en'] ?? null,
                    'scoring_engine_key' => $entry['key'] ?? null,
                    'sort_order'         => $order,
                    'updated_at'         => now(),
                ]);
                $updated++;
            } else {
                DB::table('question_tags')->insert([
                    'id'                 => Str::orderedUuid(),
                    'l1_domain'          => $entry['l1'],
                    'l2_pillar'          => $entry['l2'],
                    'l3_topic'           => $entry['label_zh'],
                    'slug'               => $entry['slug'],
                    'label_zh'           => $entry['label_zh'],
                    'label_en'           => $entry['label_en'] ?? null,
                    'scoring_engine_key' => $entry['key'] ?? null,
                    'sort_order'         => $order,
                    'deprecated_at'      => null,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
                $created++;
            }
            $order++;
        }

        $this->command->info("✓ 標籤庫分類：新增 {$created} 筆，更新 {$updated} 筆");
    }

    private function tags(): array
    {
        return [
            // ── L1: ISO26000 ─────────────────────────────────────────────────────
            // ISO 26000:2010 七大核心主題 → L2；各核心主題議題 → L3

            // 1. 組織治理 (Organizational Governance)
            ['l1' => 'ISO26000', 'l2' => '組織治理', 'slug' => 'iso26k.gov.accountability',       'label_zh' => '課責機制與決策流程',       'label_en' => 'Accountability & Decision-making',                  'key' => 'iso26k_accountability_v1'],
            ['l1' => 'ISO26000', 'l2' => '組織治理', 'slug' => 'iso26k.gov.stakeholder',          'label_zh' => '利害關係人識別與參與',     'label_en' => 'Stakeholder Identification & Engagement',           'key' => 'iso26k_stakeholder_v1'],
            ['l1' => 'ISO26000', 'l2' => '組織治理', 'slug' => 'iso26k.gov.disclosure',           'label_zh' => 'ESG 治理資訊揭露',         'label_en' => 'ESG Governance & Transparency Disclosure',          'key' => 'iso26k_disclosure_v1'],

            // 2. 人權 (Human Rights)
            ['l1' => 'ISO26000', 'l2' => '人權',     'slug' => 'iso26k.hr.due_diligence',         'label_zh' => '人權盡職調查',             'label_en' => 'Human Rights Due Diligence (HRDD)',                 'key' => 'iso26k_hrdd_v1'],
            ['l1' => 'ISO26000', 'l2' => '人權',     'slug' => 'iso26k.hr.grievance',             'label_zh' => '申訴與救濟機制',           'label_en' => 'Grievance & Remedy Mechanisms (UNGPs)',             'key' => 'iso26k_grievance_v1'],
            ['l1' => 'ISO26000', 'l2' => '人權',     'slug' => 'iso26k.hr.complicity',            'label_zh' => '迴避共謀責任',             'label_en' => 'Avoidance of Complicity',                           'key' => 'iso26k_complicity_v1'],
            ['l1' => 'ISO26000', 'l2' => '人權',     'slug' => 'iso26k.hr.vulnerable',            'label_zh' => '脆弱群體保護',             'label_en' => 'Protection of Vulnerable Groups',                   'key' => 'iso26k_vulnerable_v1'],
            ['l1' => 'ISO26000', 'l2' => '人權',     'slug' => 'iso26k.hr.sc_risk',               'label_zh' => '供應鏈人權風險',           'label_en' => 'Human Rights Risks in Supply Chain',                'key' => 'iso26k_sc_hr_risk_v1'],

            // 3. 勞工實踐 (Labour Practices)
            ['l1' => 'ISO26000', 'l2' => '勞工實踐', 'slug' => 'iso26k.labor.employment_rel',     'label_zh' => '就業關係正規化',           'label_en' => 'Employment Relationships (ILO)',                    'key' => 'iso26k_employment_v1'],
            ['l1' => 'ISO26000', 'l2' => '勞工實踐', 'slug' => 'iso26k.labor.conditions',         'label_zh' => '勞動條件與社會保障',       'label_en' => 'Labour Conditions & Social Protection',             'key' => 'iso26k_labor_cond_v1'],
            ['l1' => 'ISO26000', 'l2' => '勞工實踐', 'slug' => 'iso26k.labor.social_dialogue',    'label_zh' => '社會對話與集體談判',       'label_en' => 'Social Dialogue & Collective Bargaining (SD/CB)',   'key' => 'iso26k_social_dialog_v1'],
            ['l1' => 'ISO26000', 'l2' => '勞工實踐', 'slug' => 'iso26k.labor.ohs',                'label_zh' => '職場健康安全管理',         'label_en' => 'Occupational Health & Safety (OHS / ISO 45001)',    'key' => 'iso26k_ohs_v1'],
            ['l1' => 'ISO26000', 'l2' => '勞工實踐', 'slug' => 'iso26k.labor.development',        'label_zh' => '人力培訓與職涯發展',       'label_en' => 'Training, Development & Career Growth',             'key' => 'iso26k_hr_dev_v1'],

            // 4. 環境 (The Environment)
            ['l1' => 'ISO26000', 'l2' => '環境',     'slug' => 'iso26k.env.precaution',           'label_zh' => '預防原則應用',             'label_en' => 'Precautionary Approach (Rio Principle 15)',         'key' => 'iso26k_precaution_v1'],
            ['l1' => 'ISO26000', 'l2' => '環境',     'slug' => 'iso26k.env.pollution',            'label_zh' => '污染防制與廢棄物管理',     'label_en' => 'Pollution Prevention & Waste Management',           'key' => 'iso26k_pollution_v1'],
            ['l1' => 'ISO26000', 'l2' => '環境',     'slug' => 'iso26k.env.resource',             'label_zh' => '永續資源使用',             'label_en' => 'Sustainable Resource Use (Circular Economy)',       'key' => 'iso26k_resource_v1'],
            ['l1' => 'ISO26000', 'l2' => '環境',     'slug' => 'iso26k.env.climate',              'label_zh' => '氣候變遷減緩與調適',       'label_en' => 'Climate Change Mitigation & Adaptation (TCFD)',     'key' => 'iso26k_climate_v1'],
            ['l1' => 'ISO26000', 'l2' => '環境',     'slug' => 'iso26k.env.biodiversity',         'label_zh' => '生物多樣性與生態系服務',   'label_en' => 'Biodiversity & Ecosystem Services (TNFD)',          'key' => 'iso26k_biodiversity_v1'],

            // 5. 公平營運實踐 (Fair Operating Practices)
            ['l1' => 'ISO26000', 'l2' => '公平營運實踐', 'slug' => 'iso26k.fair.anti_corruption', 'label_zh' => '反腐敗與反賄賂',           'label_en' => 'Anti-Corruption & Bribery (FCPA / UK Bribery Act)', 'key' => 'iso26k_anti_corrupt_v1'],
            ['l1' => 'ISO26000', 'l2' => '公平營運實踐', 'slug' => 'iso26k.fair.competition',     'label_zh' => '公平競爭與反壟斷',         'label_en' => 'Fair Competition & Anti-Trust',                     'key' => 'iso26k_competition_v1'],
            ['l1' => 'ISO26000', 'l2' => '公平營運實踐', 'slug' => 'iso26k.fair.sc_promotion',    'label_zh' => '推動供應鏈社會責任',       'label_en' => 'Promoting SR in Supply Chain (CoC)',                'key' => 'iso26k_sc_sr_v1'],
            ['l1' => 'ISO26000', 'l2' => '公平營運實踐', 'slug' => 'iso26k.fair.property_rights', 'label_zh' => '財產權與智慧財產尊重',     'label_en' => 'Respect for Property Rights (IP)',                  'key' => 'iso26k_property_v1'],

            // 6. 消費者議題 (Consumer Issues)
            ['l1' => 'ISO26000', 'l2' => '消費者議題', 'slug' => 'iso26k.consumer.marketing',     'label_zh' => '公平行銷與真實揭露',       'label_en' => 'Fair Marketing & Truthful Disclosure',              'key' => 'iso26k_marketing_v1'],
            ['l1' => 'ISO26000', 'l2' => '消費者議題', 'slug' => 'iso26k.consumer.safety',        'label_zh' => '產品安全與消費者健康',     'label_en' => 'Product Safety & Consumer Health Protection',       'key' => 'iso26k_consumer_safety_v1'],
            ['l1' => 'ISO26000', 'l2' => '消費者議題', 'slug' => 'iso26k.consumer.sustainable',   'label_zh' => '永續消費促進',             'label_en' => 'Promoting Sustainable Consumption',                 'key' => 'iso26k_sustainable_v1'],
            ['l1' => 'ISO26000', 'l2' => '消費者議題', 'slug' => 'iso26k.consumer.data_privacy',  'label_zh' => '個資保護與數位權利',       'label_en' => 'Data Privacy & Digital Rights (GDPR)',              'key' => 'iso26k_data_privacy_v1'],

            // 7. 社區參與與發展 (Community Involvement and Development)
            ['l1' => 'ISO26000', 'l2' => '社區參與與發展', 'slug' => 'iso26k.comm.involvement',   'label_zh' => '社區參與與在地投資',       'label_en' => 'Community Involvement & Local Investment',          'key' => 'iso26k_comm_involve_v1'],
            ['l1' => 'ISO26000', 'l2' => '社區參與與發展', 'slug' => 'iso26k.comm.employment',    'label_zh' => '就業創造與技能提升',       'label_en' => 'Employment Creation & Skills Development',          'key' => 'iso26k_comm_employ_v1'],
            ['l1' => 'ISO26000', 'l2' => '社區參與與發展', 'slug' => 'iso26k.comm.culture',       'label_zh' => '在地文化與傳統保護',       'label_en' => 'Local Culture & Heritage Respect',                  'key' => 'iso26k_culture_v1'],
            ['l1' => 'ISO26000', 'l2' => '社區參與與發展', 'slug' => 'iso26k.comm.sia',           'label_zh' => '社會影響評估',             'label_en' => 'Social Impact Assessment (SIA)',                    'key' => 'iso26k_sia_v1'],

            // ── L1: ESG ──────────────────────────────────────────────────────────

            // E — 環境（L2 統一為「環境」，原環境管理 + 供應鏈環境合併）
            ['l1' => 'ESG', 'l2' => '環境', 'slug' => 'esg.env.ghg_emission',       'label_zh' => '溫室氣體排放',         'label_en' => 'GHG Emissions',                 'key' => 'ghg_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '環境', 'slug' => 'esg.env.energy_consumption',  'label_zh' => '能源消耗管理',         'label_en' => 'Energy Consumption',            'key' => 'energy_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '環境', 'slug' => 'esg.env.water_usage',         'label_zh' => '用水管理',             'label_en' => 'Water Usage',                   'key' => 'water_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '環境', 'slug' => 'esg.env.waste_management',    'label_zh' => '廢棄物管理',           'label_en' => 'Waste Management',              'key' => 'waste_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '環境', 'slug' => 'esg.env.biodiversity',        'label_zh' => '生物多樣性',           'label_en' => 'Biodiversity',                  'key' => 'biodiversity_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '環境', 'slug' => 'esg.env.chemical_mgmt',       'label_zh' => '化學品管理',           'label_en' => 'Chemical Management',           'key' => 'chemical_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '環境', 'slug' => 'esg.env.carbon_neutrality',   'label_zh' => '碳中和目標與路徑',     'label_en' => 'Carbon Neutrality Target',      'key' => 'decarb_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '環境', 'slug' => 'esg.sc_env.scope3',           'label_zh' => '範疇三排放（供應鏈）', 'label_en' => 'Scope 3 Emissions',             'key' => 'scope3_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '環境', 'slug' => 'esg.sc_env.raw_material',     'label_zh' => '原料溯源與永續採購',   'label_en' => 'Raw Material Sourcing',         'key' => 'raw_material_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '環境', 'slug' => 'esg.sc_env.packaging',        'label_zh' => '包裝與減塑',           'label_en' => 'Packaging & Plastic Reduction', 'key' => 'packaging_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '環境', 'slug' => 'esg.sc_env.eudr',             'label_zh' => 'EUDR 森林砍伐合規',    'label_en' => 'EUDR Deforestation Compliance', 'key' => 'eudr_scoring_v1'],

            // S — 社會（L2 統一為「社會」，原勞工人權 + 職場安全 + 社區與消費者合併）
            ['l1' => 'ESG', 'l2' => '社會', 'slug' => 'esg.labor.forced_labor',      'label_zh' => '強迫勞動防制',         'label_en' => 'Forced Labor Prevention',       'key' => 'labor_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '社會', 'slug' => 'esg.labor.child_labor',       'label_zh' => '童工禁止',             'label_en' => 'Child Labor Prohibition',       'key' => 'child_labor_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '社會', 'slug' => 'esg.labor.working_hours',     'label_zh' => '工時與休息管理',       'label_en' => 'Working Hours Management',      'key' => 'working_hours_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '社會', 'slug' => 'esg.labor.wages',             'label_zh' => '工資與薪酬公平',       'label_en' => 'Fair Wages & Compensation',     'key' => 'wages_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '社會', 'slug' => 'esg.labor.freedom_assoc',     'label_zh' => '結社自由與集體談判',   'label_en' => 'Freedom of Association',        'key' => 'freedom_assoc_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '社會', 'slug' => 'esg.labor.discrimination',    'label_zh' => '平等與反歧視',         'label_en' => 'Non-Discrimination',            'key' => 'discrimination_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '社會', 'slug' => 'esg.ohs.safety_mgmt',         'label_zh' => '職安管理系統',         'label_en' => 'OHS Management System',         'key' => 'ohs_mgmt_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '社會', 'slug' => 'esg.ohs.incident_rate',       'label_zh' => '事故率與統計',         'label_en' => 'Incident Rate & Statistics',    'key' => 'ohs_incident_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '社會', 'slug' => 'esg.ohs.ppe',                 'label_zh' => '個人防護裝備（PPE）',  'label_en' => 'Personal Protective Equipment', 'key' => 'ppe_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '社會', 'slug' => 'esg.ohs.training',            'label_zh' => '安全教育訓練',         'label_en' => 'Safety Training',               'key' => 'safety_training_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '社會', 'slug' => 'esg.comm.community_invest',   'label_zh' => '社區投資與參與',       'label_en' => 'Community Investment',          'key' => 'community_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '社會', 'slug' => 'esg.comm.product_safety',     'label_zh' => '產品安全與責任',       'label_en' => 'Product Safety & Liability',    'key' => 'product_safety_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '社會', 'slug' => 'esg.comm.data_privacy',       'label_zh' => '資料隱私保護',         'label_en' => 'Data Privacy',                  'key' => 'data_privacy_scoring_v1'],

            // G — 治理（L2 統一為「治理」，原公司治理）
            ['l1' => 'ESG', 'l2' => '治理', 'slug' => 'esg.gov.code_of_conduct',     'label_zh' => '行為準則與倫理',       'label_en' => 'Code of Conduct & Ethics',      'key' => 'conduct_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '治理', 'slug' => 'esg.gov.anti_corruption',     'label_zh' => '反腐敗與反賄賂',       'label_en' => 'Anti-Corruption & Bribery',     'key' => 'anti_corruption_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '治理', 'slug' => 'esg.gov.whistleblower',       'label_zh' => '吹哨人保護機制',       'label_en' => 'Whistleblower Protection',      'key' => 'whistleblower_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '治理', 'slug' => 'esg.gov.transparency',        'label_zh' => '資訊透明度與揭露',     'label_en' => 'Transparency & Disclosure',     'key' => 'transparency_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '治理', 'slug' => 'esg.gov.board',               'label_zh' => '董事會組成與獨立性',   'label_en' => 'Board Composition',             'key' => 'board_scoring_v1'],
            ['l1' => 'ESG', 'l2' => '治理', 'slug' => 'esg.gov.supplier_code',       'label_zh' => '供應商行為準則推廣',   'label_en' => 'Supplier Code of Conduct',      'key' => 'supplier_coc_scoring_v1'],

            // ── L1: ISO28000（供應鏈安全管理）────────────────────────────────────

            ['l1' => 'ISO28000', 'l2' => '認證與管理體系', 'slug' => 'iso28k.cert.standards',       'label_zh' => 'ISO28000/C-TPAT/AEO 認證',     'label_en' => 'Supply Chain Security Certification', 'key' => 'iso28k_cert_v1'],
            ['l1' => 'ISO28000', 'l2' => '認證與管理體系', 'slug' => 'iso28k.cert.internal_audit',  'label_zh' => '內部稽核機制',                 'label_en' => 'Internal Security Audit',             'key' => 'iso28k_audit_v1'],
            ['l1' => 'ISO28000', 'l2' => '認證與管理體系', 'slug' => 'iso28k.cert.mgmt_review',     'label_zh' => '管理審查與高層承諾',           'label_en' => 'Management Review & Commitment',      'key' => 'iso28k_mgmt_review_v1'],

            ['l1' => 'ISO28000', 'l2' => '實體與人員安全', 'slug' => 'iso28k.physical.facility',    'label_zh' => '實體安全設施（圍籬/監視）',    'label_en' => 'Physical Security Infrastructure',    'key' => 'iso28k_facility_v1'],
            ['l1' => 'ISO28000', 'l2' => '實體與人員安全', 'slug' => 'iso28k.physical.access',      'label_zh' => '門禁管控與身份識別',           'label_en' => 'Access Control & Identification',     'key' => 'iso28k_access_ctrl_v1'],
            ['l1' => 'ISO28000', 'l2' => '實體與人員安全', 'slug' => 'iso28k.physical.background',  'label_zh' => '人員背景查核',                 'label_en' => 'Personnel Background Check',          'key' => 'iso28k_background_v1'],

            ['l1' => 'ISO28000', 'l2' => '貨物與物流安全', 'slug' => 'iso28k.cargo.handling',       'label_zh' => '裝卸貨物場地管控',             'label_en' => 'Cargo Handling Area Control',         'key' => 'iso28k_cargo_handling_v1'],
            ['l1' => 'ISO28000', 'l2' => '貨物與物流安全', 'slug' => 'iso28k.cargo.seal',           'label_zh' => '貨物封條管理（ISO 17712）',    'label_en' => 'Cargo Seal Management (ISO 17712)',   'key' => 'iso28k_cargo_seal_v1'],
            ['l1' => 'ISO28000', 'l2' => '貨物與物流安全', 'slug' => 'iso28k.cargo.gps_tracking',   'label_zh' => 'GPS 追蹤與運輸安全',           'label_en' => 'GPS Tracking & Transport Security',   'key' => 'iso28k_gps_v1'],
            ['l1' => 'ISO28000', 'l2' => '貨物與物流安全', 'slug' => 'iso28k.cargo.logistics_eval', 'label_zh' => '物流供應商安全評估',           'label_en' => 'Logistics Provider Security Review',  'key' => 'iso28k_logistics_v1'],

            ['l1' => 'ISO28000', 'l2' => '資訊安全與韌性', 'slug' => 'iso28k.infosec.network',      'label_zh' => '網路與資訊安全防護',           'label_en' => 'Network & Information Security',      'key' => 'iso28k_network_sec_v1'],
            ['l1' => 'ISO28000', 'l2' => '資訊安全與韌性', 'slug' => 'iso28k.infosec.backup',       'label_zh' => '資料備援與異地備份',           'label_en' => 'Data Backup & Disaster Recovery',     'key' => 'iso28k_backup_v1'],
            ['l1' => 'ISO28000', 'l2' => '資訊安全與韌性', 'slug' => 'iso28k.infosec.incident',     'label_zh' => '資安事件通報與應變',           'label_en' => 'Security Incident Response',          'key' => 'iso28k_incident_v1'],
            ['l1' => 'ISO28000', 'l2' => '資訊安全與韌性', 'slug' => 'iso28k.infosec.bcp',          'label_zh' => '業務持續計畫（BCP/EPRP）',     'label_en' => 'Business Continuity Plan (BCP/EPRP)', 'key' => 'iso28k_bcp_v1'],

            // ── L1: ISO20400 ─────────────────────────────────────────────────────

            ['l1' => 'ISO20400', 'l2' => '採購政策',    'slug' => 'iso20400.policy.commitment',   'label_zh' => '永續採購承諾聲明',   'label_en' => 'Sustainable Procurement Commitment', 'key' => 'procure_commit_v1'],
            ['l1' => 'ISO20400', 'l2' => '採購政策',    'slug' => 'iso20400.policy.criteria',     'label_zh' => '採購評選標準整合',   'label_en' => 'Procurement Criteria Integration',  'key' => 'procure_criteria_v1'],
            ['l1' => 'ISO20400', 'l2' => '採購政策',    'slug' => 'iso20400.policy.supplier_dev', 'label_zh' => '供應商能力建構計畫', 'label_en' => 'Supplier Development Program',      'key' => 'supplier_dev_v1'],

            ['l1' => 'ISO20400', 'l2' => '風險管理',    'slug' => 'iso20400.risk.assessment',     'label_zh' => '永續風險評估流程',   'label_en' => 'Sustainability Risk Assessment',     'key' => 'risk_scoring_v1'],
            ['l1' => 'ISO20400', 'l2' => '風險管理',    'slug' => 'iso20400.risk.due_diligence',  'label_zh' => '人權與環境盡職調查', 'label_en' => 'Human Rights Due Diligence',         'key' => 'procure_dd_v1'],
            ['l1' => 'ISO20400', 'l2' => '風險管理',    'slug' => 'iso20400.risk.country',        'label_zh' => '國家/地區風險評估',  'label_en' => 'Country Risk Assessment',            'key' => 'country_risk_scoring_v1'],

            ['l1' => 'ISO20400', 'l2' => '績效評估',    'slug' => 'iso20400.perf.kpi',            'label_zh' => '永續採購 KPI 設定',  'label_en' => 'Sustainable Procurement KPIs',       'key' => 'procure_kpi_v1'],
            ['l1' => 'ISO20400', 'l2' => '績效評估',    'slug' => 'iso20400.perf.audit',          'label_zh' => '定期稽核與查核',     'label_en' => 'Regular Audit & Inspection',         'key' => 'procure_audit_v1'],
            ['l1' => 'ISO20400', 'l2' => '績效評估',    'slug' => 'iso20400.perf.report',         'label_zh' => '永續採購報告揭露',   'label_en' => 'Sustainable Procurement Reporting',  'key' => 'procure_report_v1'],

            // ── L1: Geo-Risk ──────────────────────────────────────────────────────

            ['l1' => 'Geo-Risk', 'l2' => '地緣政治風險', 'slug' => 'geo_risk.geopo.sanctions',    'label_zh' => '制裁與出口管制',     'label_en' => 'Sanctions & Export Controls',        'key' => 'sanctions_scoring_v1'],
            ['l1' => 'Geo-Risk', 'l2' => '地緣政治風險', 'slug' => 'geo_risk.geopo.conflict',     'label_zh' => '衝突礦物與風險區域', 'label_en' => 'Conflict Minerals & High-Risk Areas','key' => 'conflict_mineral_v1'],
            ['l1' => 'Geo-Risk', 'l2' => '地緣政治風險', 'slug' => 'geo_risk.geopo.tariff',       'label_zh' => '關稅與貿易壁壘',     'label_en' => 'Tariff & Trade Barriers',            'key' => 'tariff_scoring_v1'],

            ['l1' => 'Geo-Risk', 'l2' => '物流/天災',    'slug' => 'geo_risk.logistics.disaster', 'label_zh' => '自然災害韌性',       'label_en' => 'Natural Disaster Resilience',        'key' => 'disaster_resilience_v1'],
            ['l1' => 'Geo-Risk', 'l2' => '物流/天災',    'slug' => 'geo_risk.logistics.bcp',      'label_zh' => '業務持續計畫（BCP）','label_en' => 'Business Continuity Plan (BCP)',     'key' => 'geo_bcp_v1'],
            ['l1' => 'Geo-Risk', 'l2' => '物流/天災',    'slug' => 'geo_risk.logistics.single_src','label_zh' => '單一來源依賴風險',  'label_en' => 'Single-Source Dependency Risk',      'key' => 'single_source_risk_v1'],

            // Geo-Risk → 政治風險（L3 展開）
            ['l1' => 'Geo-Risk', 'l2' => '政治風險', 'slug' => 'geo_risk.political.gov_stability',  'label_zh' => '政府穩定性評估',       'label_en' => 'Government Stability Assessment',        'key' => 'political_stability_v1'],
            ['l1' => 'Geo-Risk', 'l2' => '政治風險', 'slug' => 'geo_risk.political.policy_change',  'label_zh' => '政策與法規突變風險',   'label_en' => 'Policy & Regulatory Sudden Change Risk', 'key' => 'policy_change_v1'],
            ['l1' => 'Geo-Risk', 'l2' => '政治風險', 'slug' => 'geo_risk.political.nationalization', 'label_zh' => '國有化與財產徵收風險', 'label_en' => 'Nationalization & Expropriation Risk',  'key' => 'nationalization_v1'],
            ['l1' => 'Geo-Risk', 'l2' => '政治風險', 'slug' => 'geo_risk.political.violence',        'label_zh' => '政治暴力與社會動盪',   'label_en' => 'Political Violence & Civil Unrest',      'key' => 'political_violence_v1'],

            // Geo-Risk → 法規風險（L3 展開）
            ['l1' => 'Geo-Risk', 'l2' => '法規風險', 'slug' => 'geo_risk.regulatory.compliance',    'label_zh' => '法規遵循複雜度',       'label_en' => 'Regulatory Compliance Complexity',       'key' => 'regulatory_compliance_v1'],
            ['l1' => 'Geo-Risk', 'l2' => '法規風險', 'slug' => 'geo_risk.regulatory.labor_law',     'label_zh' => '勞工法規變動風險',     'label_en' => 'Labor Law Change Risk',                  'key' => 'labor_law_risk_v1'],
            ['l1' => 'Geo-Risk', 'l2' => '法規風險', 'slug' => 'geo_risk.regulatory.env_regulation','label_zh' => '環境法規要求',         'label_en' => 'Environmental Regulation Requirements',  'key' => 'env_regulation_v1'],
            ['l1' => 'Geo-Risk', 'l2' => '法規風險', 'slug' => 'geo_risk.regulatory.customs',       'label_zh' => '海關與進出口法規',     'label_en' => 'Customs & Import/Export Regulations',    'key' => 'customs_regulation_v1'],

            // Geo-Risk → 環境風險（L3 展開）
            ['l1' => 'Geo-Risk', 'l2' => '環境風險', 'slug' => 'geo_risk.env_risk.climate_physical','label_zh' => '氣候實體風險',         'label_en' => 'Climate Physical Risk (Heat/Flood)',     'key' => 'climate_physical_v1'],
            ['l1' => 'Geo-Risk', 'l2' => '環境風險', 'slug' => 'geo_risk.env_risk.water_scarcity',  'label_zh' => '水資源短缺風險',       'label_en' => 'Water Scarcity Risk',                    'key' => 'water_scarcity_v1'],
            ['l1' => 'Geo-Risk', 'l2' => '環境風險', 'slug' => 'geo_risk.env_risk.pollution',        'label_zh' => '環境污染與法遵風險',   'label_en' => 'Environmental Pollution & Compliance',   'key' => 'env_pollution_v1'],

            // Geo-Risk → 社會風險（L3 展開）
            ['l1' => 'Geo-Risk', 'l2' => '社會風險', 'slug' => 'geo_risk.social_risk.labor_unrest',  'label_zh' => '勞工抗議與社會動盪',  'label_en' => 'Labor Unrest & Social Instability',      'key' => 'labor_unrest_v1'],
            ['l1' => 'Geo-Risk', 'l2' => '社會風險', 'slug' => 'geo_risk.social_risk.community',     'label_zh' => '社區衝突風險',         'label_en' => 'Community Conflict Risk',                'key' => 'community_conflict_v1'],
            ['l1' => 'Geo-Risk', 'l2' => '社會風險', 'slug' => 'geo_risk.social_risk.human_rights',  'label_zh' => '人權侵害地區風險',    'label_en' => 'Human Rights Abuse Region Risk',         'key' => 'hr_abuse_region_v1'],

            // ISO20400 → 利害關係人（L3 展開）
            ['l1' => 'ISO20400', 'l2' => '利害關係人', 'slug' => 'iso20400.stakeholder.identification', 'label_zh' => '利害關係人識別與分析', 'label_en' => 'Stakeholder Identification & Analysis',  'key' => 'stakeholder_id_v1'],
            ['l1' => 'ISO20400', 'l2' => '利害關係人', 'slug' => 'iso20400.stakeholder.engagement',     'label_zh' => '溝通與參與機制',       'label_en' => 'Stakeholder Communication & Engagement', 'key' => 'stakeholder_engage_v1'],

            // ISO20400 → 報告揭露（L3 展開）
            ['l1' => 'ISO20400', 'l2' => '報告揭露', 'slug' => 'iso20400.reporting.transparency',  'label_zh' => '採購透明度聲明',       'label_en' => 'Procurement Transparency Statement',     'key' => 'procure_transparency_v1'],
            ['l1' => 'ISO20400', 'l2' => '報告揭露', 'slug' => 'iso20400.reporting.sustainability', 'label_zh' => '永續採購績效揭露',     'label_en' => 'Sustainable Procurement Performance',    'key' => 'procure_perf_v1'],

            // ISO20400 → 盡職調查（L3 展開）
            ['l1' => 'ISO20400', 'l2' => '盡職調查', 'slug' => 'iso20400.due_diligence.supplier',    'label_zh' => '供應商盡職調查程序',   'label_en' => 'Supplier Due Diligence Process',         'key' => 'supplier_dd_v1'],
            ['l1' => 'ISO20400', 'l2' => '盡職調查', 'slug' => 'iso20400.due_diligence.risk_process', 'label_zh' => '風險評估程序建立',    'label_en' => 'Risk Assessment Process Establishment',  'key' => 'dd_risk_process_v1'],

            // ISO20400 → 能力建構（L3 展開）
            ['l1' => 'ISO20400', 'l2' => '能力建構', 'slug' => 'iso20400.capacity.internal',  'label_zh' => '內部永續採購培訓',     'label_en' => 'Internal Sustainable Procurement Training', 'key' => 'internal_training_v1'],
            ['l1' => 'ISO20400', 'l2' => '能力建構', 'slug' => 'iso20400.capacity.supplier',  'label_zh' => '供應商能力提升計畫',   'label_en' => 'Supplier Capability Improvement Program',  'key' => 'supplier_cap_v1'],

            // ISO20400 → 行動計畫（L3 展開）
            ['l1' => 'ISO20400', 'l2' => '行動計畫', 'slug' => 'iso20400.action.target',      'label_zh' => '永續採購目標設定',     'label_en' => 'Sustainable Procurement Target Setting',   'key' => 'procure_target_v1'],
            ['l1' => 'ISO20400', 'l2' => '行動計畫', 'slug' => 'iso20400.action.improvement', 'label_zh' => '改善計畫制定與追蹤',   'label_en' => 'Improvement Plan & Progress Tracking',     'key' => 'procure_improve_v1'],

            // ── L1: Product-Compliance ────────────────────────────────────────────

            ['l1' => 'Product-Compliance', 'l2' => 'CBAM合規',  'slug' => 'prod_comp.cbam.embedded_emission', 'label_zh' => '內含碳排計算方法',       'label_en' => 'Embedded Carbon Calculation (ECA/CBAM)',       'key' => 'cbam_scoring_v1'],
            ['l1' => 'Product-Compliance', 'l2' => 'CBAM合規',  'slug' => 'prod_comp.cbam.reporting',         'label_zh' => 'CBAM 申報文件準備',       'label_en' => 'CBAM Reporting & Declaration (CBAM Art.6)',    'key' => 'cbam_reporting_v1'],
            ['l1' => 'Product-Compliance', 'l2' => 'CBAM合規',  'slug' => 'prod_comp.cbam.product_coverage',  'label_zh' => 'CBAM 適用產品確認',       'label_en' => 'CBAM Product Coverage Check (HS Code)',        'key' => 'cbam_coverage_v1'],

            ['l1' => 'Product-Compliance', 'l2' => 'EUDR合規',  'slug' => 'prod_comp.eudr.dds',               'label_zh' => 'EUDR 盡職調查聲明（DDS）','label_en' => 'Due Diligence Statement (DDS / EUDR)',         'key' => 'eudr_dds_v1'],
            ['l1' => 'Product-Compliance', 'l2' => 'EUDR合規',  'slug' => 'prod_comp.eudr.traceability',      'label_zh' => '原料溯源至農場/地塊',     'label_en' => 'Farm-level Traceability (Geo-polygon)',        'key' => 'eudr_traceability_v1'],
            ['l1' => 'Product-Compliance', 'l2' => 'EUDR合規',  'slug' => 'prod_comp.eudr.certification',     'label_zh' => '森林認證（FSC/PEFC等）',  'label_en' => 'Forest Certification (FSC / PEFC / RSPO)',    'key' => 'eudr_cert_v1'],

            ['l1' => 'Product-Compliance', 'l2' => '化學法規',  'slug' => 'prod_comp.chem.reach',             'label_zh' => 'REACH 有害物質管制',      'label_en' => 'REACH SVHC & Restriction (EU 1907/2006)',      'key' => 'reach_scoring_v1'],
            ['l1' => 'Product-Compliance', 'l2' => '化學法規',  'slug' => 'prod_comp.chem.rohs',              'label_zh' => 'RoHS 限制物質',           'label_en' => 'RoHS Restricted Substances (EU 2011/65)',      'key' => 'rohs_scoring_v1'],
            ['l1' => 'Product-Compliance', 'l2' => '化學法規',  'slug' => 'prod_comp.chem.sds',               'label_zh' => '安全資料表（SDS）管理',   'label_en' => 'Safety Data Sheet (SDS / GHS)',                'key' => 'sds_scoring_v1'],
            ['l1' => 'Product-Compliance', 'l2' => '化學法規',  'slug' => 'prod_comp.chem.pfas',              'label_zh' => 'PFAS/PFOA 持久性化學品管制','label_en' => 'PFAS/PFOA Restriction (EU POPs / OECD)',      'key' => 'pfas_scoring_v1'],
            ['l1' => 'Product-Compliance', 'l2' => '化學法規',  'slug' => 'prod_comp.chem.prop65',            'label_zh' => 'Prop 65 加州65號提案',     'label_en' => 'California Prop 65 Warning Requirement',       'key' => 'prop65_scoring_v1'],
            ['l1' => 'Product-Compliance', 'l2' => '化學法規',  'slug' => 'prod_comp.chem.svhc',              'label_zh' => 'SVHC 候選清單管理',        'label_en' => 'SVHC Candidate List Management (REACH Art.59)', 'key' => 'svhc_scoring_v1'],
            ['l1' => 'Product-Compliance', 'l2' => '化學法規',  'slug' => 'prod_comp.chem.azo',               'label_zh' => 'Azo 偶氮染料管制',         'label_en' => 'Azo Dye Restriction (EU Dir 2002/61/EC)',       'key' => 'azo_scoring_v1'],
            ['l1' => 'Product-Compliance', 'l2' => '化學法規',  'slug' => 'prod_comp.chem.formaldehyde',      'label_zh' => '甲醛限量標準（GB/EN）',    'label_en' => 'Formaldehyde Limit (GB 18401 / EN 14362)',      'key' => 'formaldehyde_scoring_v1'],

            ['l1' => 'Product-Compliance', 'l2' => '溯源與認證', 'slug' => 'prod_comp.trace.uflpa',           'label_zh' => 'UFLPA 強迫勞動防制（棉花）','label_en' => 'Forced Labor — Cotton (UFLPA / Xinjiang)',    'key' => 'uflpa_scoring_v1'],
            ['l1' => 'Product-Compliance', 'l2' => '溯源與認證', 'slug' => 'prod_comp.trace.cmrt',            'label_zh' => '衝突礦物報告（CMRT）',    'label_en' => 'Conflict Mineral Report (CMRT / 3TG)',         'key' => 'cmrt_scoring_v1'],
            ['l1' => 'Product-Compliance', 'l2' => '溯源與認證', 'slug' => 'prod_comp.trace.origin_cert',     'label_zh' => '原產地證明',              'label_en' => 'Certificate of Origin (CoO / EUR.1)',          'key' => 'origin_cert_v1'],
        ];
    }
}
