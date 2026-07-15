<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketComplianceRuleSeeder extends Seeder
{
    public function run(): void
    {
        // scope：material=依物料群組文件需求觸發；product=該市場一律適用（產品層規則）
        $rules = [
            // ── EU ──
            ['market' => 'EU', 'doc_type' => 'EUDR_DDS',          'scope' => 'material', 'is_mandatory' => true,  'effective_from' => '2025-01-17', 'notes' => 'EU Deforestation Regulation (EUDR) — 木材、橡膠、棕櫚油等高森林風險商品'],
            ['market' => 'EU', 'doc_type' => 'CBAM_REPORT',        'scope' => 'material', 'is_mandatory' => true,  'effective_from' => '2026-01-01', 'notes' => 'Carbon Border Adjustment Mechanism — 鋼鐵/水泥/鋁/肥料/電力/氫'],
            ['market' => 'EU', 'doc_type' => 'ORIGIN_CERT',        'scope' => 'material', 'is_mandatory' => true,  'effective_from' => '2000-01-01', 'notes' => '原產地證明 — EU 關稅優惠適用'],
            ['market' => 'EU', 'doc_type' => 'DPP_DECLARATION',    'scope' => 'product',  'is_mandatory' => true,  'effective_from' => '2027-07-01', 'notes' => 'ESPR 數位產品護照（DPP）— 紡織/成衣首波授權法案，生效前為前瞻性規則'],
            ['market' => 'EU', 'doc_type' => 'SDS',                'scope' => 'material', 'is_mandatory' => true,  'effective_from' => '2008-06-01', 'notes' => 'REACH 化學品安全資料表 — 染料/助劑/整理劑等化學品'],
            // ── US ──
            ['market' => 'US', 'doc_type' => 'UFLPA_DECLARATION',  'scope' => 'material', 'is_mandatory' => true,  'effective_from' => '2022-06-21', 'notes' => 'Uyghur Forced Labor Prevention Act — 新疆生產商品'],
            ['market' => 'US', 'doc_type' => 'ORIGIN_CERT',        'scope' => 'material', 'is_mandatory' => true,  'effective_from' => '2000-01-01', 'notes' => '原產地證明 — US 關稅適用'],
            ['market' => 'US', 'doc_type' => 'CMRT',               'scope' => 'material', 'is_mandatory' => true,  'effective_from' => '2010-01-01', 'notes' => 'Conflict Minerals Reporting Template — Dodd-Frank Act §1502'],
            ['market' => 'US', 'doc_type' => 'CPSIA_CERT',         'scope' => 'product',  'is_mandatory' => false, 'effective_from' => '2009-02-10', 'notes' => 'CPSIA 兒童產品安全（鉛/易燃性）— 童裝強制；系統未分產品年齡層，列建議級提示'],
            ['market' => 'US', 'doc_type' => 'PROP65_DECLARATION', 'scope' => 'product',  'is_mandatory' => false, 'effective_from' => '2000-01-01', 'notes' => '加州 Prop 65 警示聲明 — 建議性（缺失列警告不阻擋）'],
            // ── UK ──
            ['market' => 'UK', 'doc_type' => 'ORIGIN_CERT',        'scope' => 'material', 'is_mandatory' => true,  'effective_from' => '2021-01-01', 'notes' => '原產地證明 — UK 關稅適用（脫歐後）'],
            ['market' => 'UK', 'doc_type' => 'SDS',                'scope' => 'material', 'is_mandatory' => true,  'effective_from' => '2021-01-01', 'notes' => 'UK REACH 化學品安全資料表'],
            ['market' => 'UK', 'doc_type' => 'MSA_STATEMENT',      'scope' => 'product',  'is_mandatory' => true,  'effective_from' => '2015-10-29', 'notes' => 'Modern Slavery Act 供應鏈聲明 — 年營業額 £36M 以上'],
            // ── JP ──
            ['market' => 'JP', 'doc_type' => 'ORIGIN_CERT',        'scope' => 'material', 'is_mandatory' => true,  'effective_from' => '2000-01-01', 'notes' => '原產地證明 — RCEP/CPTPP 優惠關稅'],
            ['market' => 'JP', 'doc_type' => 'FORMALDEHYDE_TEST',  'scope' => 'product',  'is_mandatory' => true,  'effective_from' => '1974-10-01', 'notes' => '有害物質含有家庭用品規制法 — 甲醛限量檢測報告（嬰幼兒 16ppm/一般 75ppm）'],
            ['market' => 'JP', 'doc_type' => 'JP_QUALITY_LABEL',   'scope' => 'product',  'is_mandatory' => true,  'effective_from' => '1962-10-01', 'notes' => '家庭用品品質表示法 — 纖維成分/洗滌標示'],
        ];

        foreach ($rules as $rule) {
            DB::table('market_compliance_rules')->updateOrInsert(
                ['market' => $rule['market'], 'doc_type' => $rule['doc_type']],
                array_merge($rule, [
                    'id'         => Str::orderedUuid()->toString(),
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // APAC 為孤兒市場代碼（市場定義與批次審查皆無），停用保留紀錄
        DB::table('market_compliance_rules')->where('market', 'APAC')->update(['is_active' => false]);
    }
}
