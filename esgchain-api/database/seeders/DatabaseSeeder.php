<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\RiskAssessment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── 清除既有業務資料（保留 migrations / roles / 非供應商 users）──
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('cap_findings')->truncate();
        DB::table('caps')->truncate();
        DB::table('supplier_disclosures')->truncate();
        DB::table('saq_score_snapshots')->truncate();
        DB::table('saq_response_reviews')->truncate();
        DB::table('saq_responses')->truncate();
        DB::table('saq_review_histories')->truncate();
        DB::table('saqs')->truncate();
        DB::table('project_questions')->truncate();
        DB::table('saq_projects')->truncate();
        DB::table('assessment_series_weights')->truncate();
        DB::table('assessment_series')->truncate();
        DB::table('saq_questions')->truncate();
        DB::table('saq_templates')->truncate();
        DB::table('shipment_line_batches')->truncate();
        DB::table('shipment_lines')->truncate();
        DB::table('shipments')->truncate();
        DB::table('production_batches')->truncate();
        DB::table('raw_material_origins')->truncate();
        DB::table('product_bom_lines')->truncate();
        DB::table('supplier_compliance_docs')->truncate();
        DB::table('trade_good_supplier_emissions')->truncate();
        DB::table('trade_good_suppliers')->truncate();
        DB::table('sales_products')->truncate();
        DB::table('risk_assessments')->truncate();
        DB::table('supplier_status_histories')->truncate();
        DB::table('supplier_contacts')->truncate();
        // 只刪除供應商角色的 users（保留 admin/buyer/sustain/analyst）
        DB::table('users')->whereNotNull('supplier_id')->delete();
        DB::table('model_has_roles')->whereIn(
            'model_id',
            DB::table('users')->whereNotNull('supplier_id')->pluck('id')
        )->delete();
        DB::table('suppliers')->truncate();
        DB::table('supplier_groups')->truncate();
        DB::table('material_groups')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── 建立角色 ──
        $roles = ['admin', 'buyer', 'sustain', 'comply', 'analyst', 'supplier', 'sup_esg'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        }

        // ── 品牌採購商內部測試帳號 ──
        $internalUsers = [
            ['name' => '系統管理員',  'email' => 'admin@esgchain.com',    'role' => 'admin'],
            ['name' => '採購主管',    'email' => 'buyer@esgchain.com',    'role' => 'buyer'],
            ['name' => '永續長',      'email' => 'sustain@esgchain.com',  'role' => 'sustain'],
            ['name' => '分析師',      'email' => 'analyst@esgchain.com',  'role' => 'analyst'],
        ];

        foreach ($internalUsers as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password' => Hash::make('demo1234')]
            );
            $user->syncRoles([$u['role']]);
        }

        // ── 子 Seeder（紡織業資料）──

        // 組織單位
        $this->call(OrganizationUnitSeeder::class);

        // 國家風險評等（勞工/環境/地緣，供 RiskAutoDerivation 使用）
        $this->call(CountryRiskRatingSeeder::class);

        // 市場合規規則（EUDR/CBAM/UFLPA 等）
        $this->call(MarketComplianceRuleSeeder::class);

        // 評分框架預設權重
        $this->call(FrameworkDefaultWeightSeeder::class);

        // SASB 產業必調主題
        $this->call(SasbRequiredTopicSeeder::class);

        // 系統設定（碳價假設等）
        $this->call(SystemSettingsSeeder::class);

        // 供應商揭露欄位定義
        $this->call(SupplierDisclosureFieldSeeder::class);

        // 供應商分組（紡織業 7 群組）
        $this->call(SupplierGroupSeeder::class);

        // 物料群組（棉紡/合纖/天然輔料/染料/金屬配件）
        $this->call(MaterialGroupSeeder::class);

        // 供應商主檔（30 間跨國紡織業廠商）+ 供應商測試帳號
        $this->call(SupplierSeeder::class);

        // 採購商產品清單（8 個紡織品類別）
        $this->call(BuyerProductSeeder::class);

        // BOM 明細（4 個產品含詳細 BOM 清單）
        $this->call(ProductBomLineSeeder::class);

        // 供應商合規文件（~24 筆，涵蓋 UFLPA/SDS/CMRT/EUDR/原產地各種狀態）
        $this->call(SupplierComplianceDocSeeder::class);

        // 看板補充文件（8 筆）
        $this->call(ComplianceDashboardSeeder::class);

        // 標籤庫完整分類（ISO26000/ESG/ISO20400/ISO28000/Geo-Risk/Product-Compliance）
        // 須在 SaqS3PTemplateSeeder 之前執行，補齊各框架的 L2/L3 分類深度
        $this->call(TagLibrarySeeder::class);

        // S³P 5 份問卷範本（ISO 26000 / 28000 / 20400 / GPR / 全維度 60 題）
        // 必須在 SaqProjectSeeder 與 SaqDemoSeeder 之前，因為後者依賴範本資料
        $this->call(SaqS3PTemplateSeeder::class);

        // 問卷專案
        $this->call(SaqProjectSeeder::class);

        // 永續倡議問卷完整 DEMO 資料（系列、專案題目、SAQ、回覆、審查歷程）
        $this->call(SaqDemoSeeder::class);

        // 問卷回覆補充（確保各 SAQ 都有完整回覆記錄）
        $this->call(SaqResponseRefillSeeder::class);

        // 貿易商品（10 筆，含 CBAM/EUDR/一般品）
        $this->call(TradeGoodSeeder::class);

        // 貿易商品系統預設 PCF 估算（須在 TradeGoodSeeder 之後）
        $this->call(TradeGoodDefaultPcfSeeder::class);

        // BuyerProduct ↔ TradeGood 出口連結 mapping
        // 已停用：buyer_product_trade_goods 表已隨 BuyerProduct→SalesProduct 合併移除，
        // 此 Seeder 邏輯尚未隨架構調整重寫，暫不執行（不影響其他示範資料）
        // $this->call(ExportLinkSeeder::class);

        // 生產批號與原料溯源示範資料
        $this->call(ProductionBatchSeeder::class);

        // 出口申報示範資料（含 EUDR DDS 草稿）
        $this->call(ShipmentSeeder::class);

        // 物料主檔
        $this->call(MaterialItemSeeder::class);

        // 物料預設碳排係數（系統預載，source=system_default）
        $this->call(MaterialItemDefaultEmissionSeeder::class);

        // PCF 快照（連結 BOM↔物料、建立供應商碳排記錄、計算快照）
        $this->call(PcfSnapshotSeeder::class);

        // 目標市場定義（系統預載）
        $this->call(MarketDefinitionSeeder::class);

        // SASB 產業分類
        $this->call(SasbDisclosureTopicSeeder::class);
        $this->call(SasbIndustrySeeder::class);

        // 補齊尚無 Seeder 覆蓋的業務資料表（customers/設施/化學品/PCF請求/CAP/揭露等）
        $this->call(DemoGapFillSeeder::class);

        // ── 風險評估資料（key 供應商）──
        $riskData = [
            'CTN-001' => ['e_probability' => 2, 'e_impact' => 2, 's_probability' => 1, 's_impact' => 2, 'g_probability' => 1, 'g_impact' => 1, 'gp_probability' => 1, 'gp_impact' => 1],
            'CTN-002' => ['e_probability' => 3, 'e_impact' => 3, 's_probability' => 3, 's_impact' => 3, 'g_probability' => 2, 'g_impact' => 2, 'gp_probability' => 3, 'gp_impact' => 3],
            'CTN-005' => ['e_probability' => 5, 'e_impact' => 5, 's_probability' => 5, 's_impact' => 5, 'g_probability' => 4, 'g_impact' => 5, 'gp_probability' => 5, 'gp_impact' => 5],
            'GMN-001' => ['e_probability' => 2, 'e_impact' => 2, 's_probability' => 2, 's_impact' => 2, 'g_probability' => 1, 'g_impact' => 2, 'gp_probability' => 2, 'gp_impact' => 2],
            'GMN-002' => ['e_probability' => 4, 'e_impact' => 4, 's_probability' => 4, 's_impact' => 4, 'g_probability' => 3, 'g_impact' => 3, 'gp_probability' => 4, 'gp_impact' => 4],
            'GMN-006' => ['e_probability' => 5, 'e_impact' => 4, 's_probability' => 5, 's_impact' => 5, 'g_probability' => 4, 'g_impact' => 4, 'gp_probability' => 5, 'gp_impact' => 5],
            'TRM-002' => ['e_probability' => 3, 'e_impact' => 3, 's_probability' => 3, 's_impact' => 3, 'g_probability' => 3, 'g_impact' => 3, 'gp_probability' => 3, 'gp_impact' => 3],
            'CHM-004' => ['e_probability' => 4, 'e_impact' => 3, 's_probability' => 3, 's_impact' => 3, 'g_probability' => 3, 'g_impact' => 3, 'gp_probability' => 4, 'gp_impact' => 3],
            'WVN-003' => ['e_probability' => 3, 'e_impact' => 3, 's_probability' => 4, 's_impact' => 4, 'g_probability' => 2, 'g_impact' => 3, 'gp_probability' => 3, 'gp_impact' => 3],
            'SYN-001' => ['e_probability' => 2, 'e_impact' => 2, 's_probability' => 1, 's_impact' => 2, 'g_probability' => 1, 'g_impact' => 1, 'gp_probability' => 1, 'gp_impact' => 2],
        ];

        foreach ($riskData as $code => $dims) {
            $supplier = Supplier::where('code', $code)->first();
            if ($supplier) {
                RiskAssessment::create(array_merge($dims, [
                    'supplier_id' => $supplier->id,
                    'assessed_at' => Carbon::now(),
                ]));
            }
        }

        // ── 情境重塑：中心廠 = 歐洲品牌服飾代工廠（成衣 OEM）──
        // 所有銷售產品轉為各式成衣、補齊布料原料、重建 BOM（冪等，須在產品/BOM/原料 seeder 之後）
        $this->call(ApparelOemDemoSeeder::class);
    }
}
