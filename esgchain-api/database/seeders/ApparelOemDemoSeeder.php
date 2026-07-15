<?php

namespace Database\Seeders;

use App\Models\BomLineSupplier;
use App\Models\CAP;
use App\Models\Customer;
use App\Models\MaterialGroup;
use App\Models\MaterialItem;
use App\Models\ProductBomLine;
use App\Models\ProductionBatch;
use App\Models\RawMaterialOrigin;
use App\Models\RiskAssessment;
use App\Models\SalesProduct;
use App\Models\Shipment;
use App\Models\ShipmentLine;
use App\Models\ShipmentLineBatch;
use App\Models\Supplier;
use App\Models\TradeGoodSupplier;
use App\Services\Risk\ImpactScoreService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 情境重塑：中心廠 = 歐洲品牌服飾代工廠（成衣 OEM）。
 *
 * 幕後假設：一家亞洲成衣代工廠，為歐洲時裝品牌生產各式成衣並出口至歐盟，
 * 因此需處理 CBAM/EUDR/UFLPA 等歐盟進口合規。
 *
 * 本 Seeder 為冪等（idempotent）轉換，可重複執行：
 *   1. 中心廠 org unit 更名為成衣製造商
 *   2. 客戶更名為歐洲時裝品牌
 *   3. 新增「布料面料」原物料群組與 7 款成衣用布料
 *   4. 將 7 個非成衣銷售產品（布料/紗線/拉鍊）就地轉換為各式成衣
 *   5. 重建這 7 款成衣的 BOM Line（面料＋輔料＋縫製服務）並指派主供應商
 */
class ApparelOemDemoSeeder extends Seeder
{
    /** 7 款轉換後成衣：舊碼 => [新碼, 中文, 英文, HS, 適用法規, 主面料材料碼, BOM 組成] */
    private const CONVERSIONS = [
        'FAB-DRI-001' => [
            'code' => 'GMN-POL-004', 'zh' => '機能排汗 Polo 衫', 'en' => 'Performance Wicking Polo',
            'hs' => '6105.20', 'regs' => ['REACH'], 'fabric' => 'RAW-FAB-001',
            'bom' => [
                ['mat' => 'RAW-FAB-001', 'name' => '機能排汗針織面料', 'qty' => 0.30, 'unit' => 'kg', 'sup' => 'WVN-002'],
                ['mat' => 'ACC-MET-002', 'name' => 'Polo 領三合扣（15mm）', 'qty' => 3, 'unit' => 'pcs', 'sup' => 'TRM-002'],
                ['mat' => 'CHM-DYE-002', 'name' => '分散染料（藏青）', 'qty' => 0.02, 'unit' => 'kg', 'sup' => 'DYE-002'],
                ['mat' => 'SVC-CMT-001', 'name' => '針織成衣縫製（Polo）', 'qty' => 1, 'unit' => 'pcs', 'sup' => 'GMN-001', 'type' => 'service'],
            ],
        ],
        'FAB-WPB-002' => [
            'code' => 'GMN-CT-005', 'zh' => '防水透氣風衣外套', 'en' => 'Waterproof Breathable Parka',
            'hs' => '6201.40', 'regs' => ['REACH', 'PFAS'], 'fabric' => 'RAW-FAB-002',
            'bom' => [
                ['mat' => 'RAW-FAB-002', 'name' => '防水透氣機能布（三層貼合）', 'qty' => 0.75, 'unit' => 'kg', 'sup' => 'WVN-001'],
                ['mat' => 'ACC-MET-001', 'name' => 'YKK 5# 防水拉鍊（65cm）', 'qty' => 2, 'unit' => 'pcs', 'sup' => 'TRM-001'],
                ['mat' => 'ACC-MET-003', 'name' => '鋁合金調節扣（下擺）', 'qty' => 2, 'unit' => 'pcs', 'sup' => 'TRM-002'],
                ['mat' => 'SVC-DYE-003', 'name' => 'DWR 防潑水塗層整理', 'qty' => 1, 'unit' => 'pcs', 'sup' => 'DYE-001', 'type' => 'service'],
                ['mat' => 'SVC-CMT-003', 'name' => '功能性外套縫製', 'qty' => 1, 'unit' => 'pcs', 'sup' => 'GMN-005', 'type' => 'service'],
            ],
        ],
        'FAB-LYO-003' => [
            'code' => 'GMN-DRS-006', 'zh' => '萊賽爾女裝洋裝', 'en' => 'Lyocell Blend Dress',
            'hs' => '6204.44', 'regs' => ['EUDR', 'REACH'], 'fabric' => 'RAW-FAB-003',
            'bom' => [
                ['mat' => 'RAW-FAB-003', 'name' => '萊賽爾梭織面料', 'qty' => 0.55, 'unit' => 'kg', 'sup' => 'WVN-003'],
                ['mat' => 'ACC-MET-002', 'name' => '隱形四合扣（背部）', 'qty' => 4, 'unit' => 'pcs', 'sup' => 'TRM-002'],
                ['mat' => 'CHM-DYE-004', 'name' => '柔軟劑（矽酮型）整理', 'qty' => 0.03, 'unit' => 'kg', 'sup' => 'CHM-002'],
                ['mat' => 'SVC-CMT-001', 'name' => '女裝洋裝縫製', 'qty' => 1, 'unit' => 'pcs', 'sup' => 'GMN-005', 'type' => 'service'],
            ],
        ],
        'FAB-ELT-004' => [
            'code' => 'GMN-LEG-007', 'zh' => '彈性運動內搭褲', 'en' => 'Stretch Athletic Leggings',
            'hs' => '6104.63', 'regs' => ['EUDR', 'REACH'], 'fabric' => 'RAW-FAB-004',
            'bom' => [
                ['mat' => 'RAW-FAB-004', 'name' => '彈性羅紋針織布（含天然橡膠包芯紗）', 'qty' => 0.35, 'unit' => 'kg', 'sup' => 'WVN-002'],
                ['mat' => 'RAW-SYN-004', 'name' => '氨綸彈性紗（腰頭）', 'qty' => 0.04, 'unit' => 'kg', 'sup' => 'SYN-001'],
                ['mat' => 'SVC-DYE-001', 'name' => '筒狀針織布染色', 'qty' => 1, 'unit' => 'pcs', 'sup' => 'DYE-002', 'type' => 'service'],
                ['mat' => 'SVC-CMT-001', 'name' => '運動內搭褲縫製', 'qty' => 1, 'unit' => 'pcs', 'sup' => 'GMN-001', 'type' => 'service'],
            ],
        ],
        'YRN-CTN-001' => [
            'code' => 'GMN-SWT-008', 'zh' => '精梳棉針織毛衣', 'en' => 'Combed Cotton Knit Sweater',
            'hs' => '6110.20', 'regs' => ['UFLPA'], 'fabric' => 'RAW-FAB-005',
            'bom' => [
                ['mat' => 'RAW-FAB-005', 'name' => '精梳棉針織布（14G）', 'qty' => 0.60, 'unit' => 'kg', 'sup' => 'WVN-003'],
                ['mat' => 'ACC-MET-002', 'name' => '椰殼鈕扣替代金屬扣', 'qty' => 5, 'unit' => 'pcs', 'sup' => 'TRM-002'],
                ['mat' => 'CHM-DYE-001', 'name' => '活性染料（酒紅）', 'qty' => 0.04, 'unit' => 'kg', 'sup' => 'CHM-001'],
                ['mat' => 'SVC-CMT-001', 'name' => '針織毛衣縫製', 'qty' => 1, 'unit' => 'pcs', 'sup' => 'GMN-002', 'type' => 'service'],
            ],
        ],
        'YRN-REC-002' => [
            'code' => 'GMN-HOD-009', 'zh' => '再生聚酯連帽衛衣', 'en' => 'Recycled Poly Hoodie',
            'hs' => '6110.30', 'regs' => ['REACH'], 'fabric' => 'RAW-FAB-006',
            'bom' => [
                ['mat' => 'RAW-FAB-006', 'name' => '再生聚酯刷毛針織布', 'qty' => 0.65, 'unit' => 'kg', 'sup' => 'WVN-002'],
                ['mat' => 'RAW-SYN-003', 'name' => '再生聚酯抽繩', 'qty' => 0.03, 'unit' => 'kg', 'sup' => 'SYN-003'],
                ['mat' => 'ACC-MET-004', 'name' => '金屬雞眼扣（帽繩）', 'qty' => 2, 'unit' => 'pcs', 'sup' => 'TRM-002'],
                ['mat' => 'SVC-CMT-001', 'name' => '連帽衛衣縫製', 'qty' => 1, 'unit' => 'pcs', 'sup' => 'GMN-003', 'type' => 'service'],
            ],
        ],
        'TRM-ZIP-001' => [
            'code' => 'GMN-SKT-010', 'zh' => '丹寧百褶短裙', 'en' => 'Denim Pleated Skirt',
            'hs' => '6204.52', 'regs' => ['UFLPA'], 'fabric' => 'RAW-FAB-007',
            'bom' => [
                ['mat' => 'RAW-FAB-007', 'name' => '10oz 丹寧梭織布', 'qty' => 0.50, 'unit' => 'kg', 'sup' => 'WVN-001'],
                ['mat' => 'ACC-MET-001', 'name' => 'YKK 5# 金屬拉鍊（18cm）', 'qty' => 1, 'unit' => 'pcs', 'sup' => 'TRM-001'],
                ['mat' => 'ACC-MET-004', 'name' => '銅製鉚釘（∅8mm）', 'qty' => 6, 'unit' => 'pcs', 'sup' => 'TRM-002'],
                ['mat' => 'SVC-DYE-002', 'name' => '石洗防縮整理', 'qty' => 1, 'unit' => 'pcs', 'sup' => 'DYE-001', 'type' => 'service'],
                ['mat' => 'SVC-CMT-002', 'name' => '丹寧成衣縫製', 'qty' => 1, 'unit' => 'pcs', 'sup' => 'GMN-002', 'type' => 'service'],
            ],
        ],
    ];

    /** 新增布料面料原物料：碼 => [中文, HS, 群組, 描述, 再生料%] */
    private const FABRICS = [
        'RAW-FAB-001' => ['機能排汗針織布', '60063200', 'fabric', '聚酯機能針織面料，吸濕排汗', null],
        'RAW-FAB-002' => ['防水透氣機能布', '59039090', 'fabric', '三層貼合防水透氣膜面料', null],
        'RAW-FAB-003' => ['萊賽爾梭織布', '55161100', 'fabric', '萊賽爾（Lyocell）混紡梭織面料', null],
        'RAW-FAB-004' => ['彈性羅紋針織布', '60041000', 'fabric', '含天然橡膠包芯紗之彈性羅紋針織布', null],
        'RAW-FAB-005' => ['精梳棉針織布', '60062100', 'fabric', '精梳棉 14G 針織面料', null],
        'RAW-FAB-006' => ['再生聚酯刷毛布', '60063200', 'fabric', '再生聚酯（rPET）刷毛針織面料', 60],
        'RAW-FAB-007' => ['丹寧梭織布 10oz', '52094200', 'fabric', '100% 棉 10oz 丹寧梭織布', null],
    ];

    /** 全部 18 款成衣的型號與生產批號：product_code => [型號, 生產批號] */
    private const MODEL_BATCH = [
        'TEX-001'     => ['VT26-TEE-001', 'LOT-260703-001'],
        'TEX-002'     => ['VT26-PNT-002', 'LOT-260703-002'],
        'TEX-003'     => ['VT26-JKT-003', 'LOT-260705-003'],
        'TEX-004'     => ['VT26-VST-004', 'LOT-260705-004'],
        'TEX-005'     => ['VT26-TEE-005', 'LOT-260707-005'],
        'TEX-006'     => ['VT26-TEE-006', 'LOT-260707-006'],
        'TEX-007'     => ['VT26-JKT-007', 'LOT-260709-007'],
        'TEX-008'     => ['VT26-SHT-008', 'LOT-260709-008'],
        'GMN-JKT-001' => ['VT26-JKT-009', 'LOT-260710-009'],
        'GMN-TEE-002' => ['VT26-TEE-010', 'LOT-260710-010'],
        'GMN-PNT-003' => ['VT26-PNT-011', 'LOT-260711-011'],
        'GMN-POL-004' => ['VT26-POL-012', 'LOT-260711-012'],
        'GMN-CT-005'  => ['VT26-CT-013',  'LOT-260712-013'],
        'GMN-DRS-006' => ['VT26-DRS-014', 'LOT-260712-014'],
        'GMN-LEG-007' => ['VT26-LEG-015', 'LOT-260713-015'],
        'GMN-SWT-008' => ['VT26-SWT-016', 'LOT-260713-016'],
        'GMN-HOD-009' => ['VT26-HOD-017', 'LOT-260714-017'],
        'GMN-SKT-010' => ['VT26-SKT-018', 'LOT-260714-018'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->reshapeCentralFactory();
            $this->reshapeCustomers();
            $fabricGroupId = $this->ensureFabricGroup();
            $this->seedFabrics($fabricGroupId);
            $this->convertToGarments();
            $this->assignModelAndBatch();
            $this->seedProductionBatches();
            // ── 第二波：情境一致性修正 ──
            $this->fixUpstreamSuppliers();   // C1 上游供應商對齊新 BOM
            $this->seedFabricEmissions();    // C2a 布料排放係數
            $this->fixProductEmissions();    // C2b 產品內含碳排
            $this->assignSpendAmounts();     // S1 採購額
            $this->rewriteCapTitles();       // R2 CAP 六維語言
            // ── 第三波：ERP 供應鏈硬規則 ──
            $this->ensureTierCoverage();     // T1–T4 全涵蓋 + BOM 標記 ERP 來源
            $this->fixUpstreamSuppliers();   // BOM 變動後重新推導上游供應商
            $this->purgeLegacyBatches();     // 清除舊 BATCH-2025 上游材料批次（未綁產品）
            $this->seedRawMaterialOrigins(); // 批次原料溯源（EUDR DDS / UFLPA 前置資料）
            $this->seedShipmentAllocations();// 出貨行 ↔ 生產批次分配（DDS 草稿資料鏈）
        });

        // 批號×市場出口合規審查 demo（呼叫審查引擎，於 transaction 外執行）
        $this->seedExportReviews();

        // S1 改了 spend → 同步重算 impact（呼叫 esgchain-ai，須在 transaction 外），並回填舊 RA 快照（R1）
        $this->recomputeImpactAndBackfill();

        $this->command?->info('ApparelOemDemoSeeder 完成：成衣情境（產品/BOM/批次）＋一致性修正（上游供應商/碳排/採購額/Impact/CAP）已就緒。');
    }

    /** 為所有成衣填入型號（生產批號屬批次實體，不寫入產品主檔）。 */
    private function assignModelAndBatch(): void
    {
        foreach (self::MODEL_BATCH as $code => [$modelNo, $batchNo]) {
            SalesProduct::where('product_code', $code)->update(['model_no' => $modelNo]);
        }
    }

    /**
     * 為每款成衣建立多筆生產批次（一對多）。
     * 批次由該成衣 BOM 中的 CMT 縫製供應商生產，碳排隨批次逐步下降（減碳敘事）。
     * 冪等：先清掉該產品既有的產品級批次再重建。
     */
    private function seedProductionBatches(): void
    {
        // 三個生產時段：月、日、數量、碳排調整（kgCO2e/件）
        $slots = [
            ['m' => 3, 'd' => 12, 'qty' => 1200, 'pcfDelta' => 0.0],
            ['m' => 5, 'd' => 14, 'qty' => 2400, 'pcfDelta' => -0.2],
            ['m' => 7, 'd' => 11, 'qty' => 1800, 'pcfDelta' => -0.4],
        ];

        $fallbackCmt = Supplier::where('code', 'GMN-001')->first();
        $cmtMaterialIds = MaterialItem::where('item_code', 'like', 'SVC-CMT-%')->pluck('id');
        // 縫製廠輪替池：同一產品的不同批次分散到不同工廠（產能/風險分散）
        $factoryPool = Supplier::whereIn('code', ['GMN-001', 'GMN-002', 'GMN-003', 'GMN-005', 'VLG-001', 'DGF-001'])
            ->orderBy('code')->pluck('id')->all();

        $i = 0;
        foreach (SalesProduct::orderBy('product_code')->get() as $product) {
            // 由 BOM 的 CMT 服務行推導主縫製廠（成衣代工廠）
            $cmtLineIds = ProductBomLine::where('sales_product_id', $product->id)
                ->whereIn('material_item_id', $cmtMaterialIds)
                ->pluck('id');
            $cmtSupplierId = BomLineSupplier::whereIn('bom_line_id', $cmtLineIds)
                ->where('role', 'primary')
                ->value('supplier_id') ?? $fallbackCmt?->id;

            // 型號序號（VT26-POL-012 → 012）作批號後綴
            $suffix = str_contains((string) $product->model_no, '-')
                ? substr((string) $product->model_no, strrpos((string) $product->model_no, '-') + 1)
                : sprintf('%03d', $i + 1);

            $basePcf = 4.5 + (($i % 5) * 0.6); // 4.5–6.9 依款式浮動

            // 冪等：清掉此產品既有批次
            ProductionBatch::where('sales_product_id', $product->id)->forceDelete();

            $batchCount = ($i % 4 === 0) ? 2 : 3; // 部分款式 2 批、部分 3 批
            for ($j = 0; $j < $batchCount; $j++) {
                $slot = $slots[$j];
                $date = Carbon::create(2026, $slot['m'], $slot['d']);
                $batchNo = sprintf('LOT-26%02d-%s', $slot['m'], $suffix);

                // 首批由 BOM 主縫製廠生產，後續批次輪替至其他工廠（分散產能與風險）
                $supplierId = $j === 0
                    ? $cmtSupplierId
                    : ($factoryPool[($i + $j) % count($factoryPool)] ?? $cmtSupplierId);
                if ($j > 0 && $supplierId === $cmtSupplierId) {
                    $supplierId = $factoryPool[($i + $j + 1) % count($factoryPool)] ?? $supplierId;
                }

                ProductionBatch::create([
                    'erp_batch_no'    => $batchNo,
                    'erp_order_no'    => sprintf('PO-2026%02d%02d-%s', $slot['m'], $slot['d'], $suffix),
                    'supplier_id'     => $supplierId,
                    'sales_product_id' => $product->id,
                    'production_date' => $date,
                    'quantity'        => $slot['qty'],
                    'unit'            => 'pcs',
                    'lot_pcf'         => round($basePcf + $slot['pcfDelta'], 2),
                    'lot_pcf_source'  => 'calculated',
                    'source'          => 'manual',
                ]);
            }
            $i++;
        }
    }

    /** 中心廠 org unit：更名為成衣代工廠（維持 TW，亞洲 OEM 出口歐盟）。 */
    private function reshapeCentralFactory(): void
    {
        DB::table('organization_units')->where('code', 'HQ')->update([
            'name'       => '維緹成衣製造股份有限公司',
            'updated_at' => now(),
        ]);
    }

    /** 客戶：更名為歐洲時裝品牌。 */
    private function reshapeCustomers(): void
    {
        $brands = [
            'CUST-DE-001' => ['name' => 'Nordwind Fashion GmbH', 'country_code' => 'DE'],
            'CUST-DE-002' => ['name' => 'Berlin Ethos Apparel GmbH', 'country_code' => 'DE'],
            'CUST-UK-001' => ['name' => 'Albion Threads Ltd.', 'country_code' => 'GB'],
        ];
        foreach ($brands as $code => $data) {
            Customer::where('code', $code)->update(array_merge($data, ['notes' => '歐洲時裝品牌客戶']));
        }
    }

    /** 建立「布料面料」原物料群組。 */
    private function ensureFabricGroup(): string
    {
        $group = MaterialGroup::firstOrCreate(
            ['name' => '布料面料'],
            [
                'group_type'         => 'material',
                'description'        => '梭織與針織布料面料（棉、合纖、萊賽爾、機能布等），為成衣主體用料。',
                'hs_code_prefixes'   => ['5407', '5408', '5512', '5513', '5514', '5515', '5516', '5903',
                                         '6001', '6002', '6003', '6004', '6005', '6006', '5209', '54', '55', '59', '60'],
                'required_doc_types' => ['ORIGIN_CERT'],
                'is_system'          => false,
            ],
        );
        return $group->id;
    }

    /** 新增 7 款成衣用布料原物料。 */
    private function seedFabrics(string $fabricGroupId): void
    {
        foreach (self::FABRICS as $code => [$name, $hs, $_g, $desc, $pcr]) {
            MaterialItem::updateOrCreate(
                ['item_code' => $code],
                [
                    'name'              => $name,
                    'hs_code'           => $hs,
                    'unit'              => 'KG',
                    'material_group_id' => $fabricGroupId,
                    'description'       => $desc,
                    'is_active'         => true,
                    'pcr_percentage'    => $pcr,
                ],
            );
        }
    }

    /** 就地把 7 個非成衣銷售產品轉為各式成衣，並重建 BOM。 */
    private function convertToGarments(): void
    {
        foreach (self::CONVERSIONS as $oldCode => $g) {
            $product = SalesProduct::where('product_code', $oldCode)->first();
            if (!$product) {
                $this->command?->warn("找不到銷售產品 {$oldCode}，略過");
                continue;
            }

            // 1) 更新產品識別為成衣（CBAM 不適用紡織品）
            $product->update([
                'name'                   => $g['zh'],
                'product_code'           => $g['code'],
                'hs_code'                => $g['hs'],
                'description'            => $g['en'],
                'is_cbam_applicable'     => false,
                'cbam_category'          => null,
                'is_eudr_applicable'     => in_array('EUDR', $g['regs'], true),
                'applicable_regulations' => $g['regs'],
            ]);

            // 2) 清掉舊 BOM 及其 AVL，重建成衣 BOM
            $oldLineIds = ProductBomLine::where('sales_product_id', $product->id)->pluck('id');
            BomLineSupplier::whereIn('bom_line_id', $oldLineIds)->delete();
            ProductBomLine::where('sales_product_id', $product->id)->forceDelete();

            foreach ($g['bom'] as $line) {
                $mat = MaterialItem::where('item_code', $line['mat'])->first();
                $bomLine = ProductBomLine::create([
                    'sales_product_id'      => $product->id,
                    'material_item_id'      => $mat?->id,
                    'material_name'         => $line['name'],
                    'hs_code'               => $mat?->hs_code,
                    'material_group_id'     => $mat?->material_group_id,
                    'material_group_source' => 'manual',
                    'linkage_status'        => $mat ? 'linked' : 'unlinked',
                    'bom_line_type'         => $line['type'] ?? 'material',
                    'quantity'              => $line['qty'],
                    'unit'                  => $line['unit'],
                    'currency'              => 'USD',
                ]);

                // 指派主供應商（AVL）
                $supplier = Supplier::where('code', $line['sup'])->first();
                if ($supplier) {
                    BomLineSupplier::create([
                        'bom_line_id' => $bomLine->id,
                        'supplier_id' => $supplier->id,
                        'role'        => 'primary',
                        'source'      => 'manual',
                        'sort_order'  => 0,
                    ]);
                }
            }
        }
    }

    // ══════════════ 第二波：情境一致性修正 ══════════════

    /** 布料排放係數 kgCO2e/kg（cradle-to-gate，system_default） */
    private const FABRIC_EMISSIONS = [
        'RAW-FAB-001' => 5.8,  // 聚酯機能針織
        'RAW-FAB-002' => 8.5,  // 三層貼合防水膜
        'RAW-FAB-003' => 4.2,  // 萊賽爾
        'RAW-FAB-004' => 5.2,  // 彈性羅紋（含天然橡膠）
        'RAW-FAB-005' => 6.5,  // 精梳棉針織
        'RAW-FAB-006' => 3.4,  // 再生聚酯（rPET 減碳）
        'RAW-FAB-007' => 7.8,  // 丹寧（高耗水耗能）
    ];

    /** 7 款轉換成衣的內含碳排 kgCO2e/件（依 BOM 面料用量×係數＋輔料/縫製推估） */
    private const PRODUCT_EMISSIONS = [
        'GMN-POL-004' => 2.6,
        'GMN-CT-005'  => 7.4,
        'GMN-DRS-006' => 3.1,
        'GMN-LEG-007' => 2.5,
        'GMN-SWT-008' => 4.8,
        'GMN-HOD-009' => 3.0,
        'GMN-SKT-010' => 4.9,  // 修正拉鍊時代殘值 0.025
    ];

    /** 年採購額（USD）：涵蓋 Impact spend 固定門檻五檔（s5≥10M/s4≥3M/s3≥1M/s2≥300k/s1<300k） */
    private const SPEND_AMOUNTS = [
        // s5 ≥10M：主力織布廠與最大成衣代工
        'WVN-001' => 12000000, 'GMN-001' => 11000000,
        // s4 3M–10M：大型成衣/化纖/紡紗
        'GMN-005' => 9500000, 'GMN-002' => 8200000, 'SYN-002' => 6800000,
        'WVN-002' => 5600000, 'SYN-001' => 4800000, 'SYN-003' => 4200000, 'CTN-001' => 3600000,
        // s3 1M–3M：染整/二線成衣/輔料主力
        'DYE-001' => 2400000, 'GMN-003' => 1800000, 'WVN-003' => 1600000,
        'CTN-002' => 1400000, 'TRM-001' => 1200000, 'SYN-004' => 1100000,
        // s2 300k–1M：化學品/配件/小型供應
        'CTN-003' => 850000, 'DYE-002' => 720000, 'TRM-002' => 680000,
        'CHM-001' => 550000, 'CHM-002' => 480000, 'GMN-004' => 420000,
        'CTN-005' => 380000, 'TRM-003' => 340000,
        // s1 <300k：零星/物流/包材
        'CHM-003' => 280000, 'LOG-001' => 260000, 'TRM-004' => 240000,
        'CTN-004' => 190000, 'CHM-004' => 160000, 'GMN-006' => 120000,
        'PKG-001' => 95000,  'LOG-002' => 85000,
    ];

    /** 舊四軸標籤 → 六維語言 */
    private const CAP_DIM_MAP = [
        'E'  => 'E1 環境管理',
        'S'  => 'E3 社會責任',
        'G'  => 'E5 公司治理',
        'GP' => 'E4 地緣風險',
    ];

    /** C1：7 款轉換成衣的上游供應商（TradeGoodSupplier）對齊新 BOM。 */
    private function fixUpstreamSuppliers(): void
    {
        $codes = array_column(self::CONVERSIONS, 'code');
        foreach (SalesProduct::whereIn('product_code', $codes)->get() as $product) {
            TradeGoodSupplier::where('trade_good_id', $product->id)->delete();

            // 由 BOM 主供應商重建：material 行 → 供應商＋該物料的 material_group
            $lines = ProductBomLine::where('sales_product_id', $product->id)
                ->whereNotNull('material_item_id')->get();

            $seen = [];
            foreach ($lines as $line) {
                $supplierId = BomLineSupplier::where('bom_line_id', $line->id)
                    ->where('role', 'primary')->value('supplier_id');
                $groupId = $line->material_group_id;
                if (!$supplierId || !$groupId) continue;

                $key = $supplierId . '|' . $groupId;
                if (isset($seen[$key])) continue;
                $seen[$key] = true;

                TradeGoodSupplier::create([
                    'trade_good_id'     => $product->id,
                    'supplier_id'       => $supplierId,
                    'material_group_id' => $groupId,
                    'notes'             => '由成衣 BOM 主供應商推導',
                ]);
            }
        }
    }

    /** C2a：新布料補排放係數（system_default，冪等）。 */
    private function seedFabricEmissions(): void
    {
        foreach (self::FABRIC_EMISSIONS as $code => $value) {
            $mat = MaterialItem::where('item_code', $code)->first();
            if (!$mat) continue;

            $base = DB::table('material_item_emissions')
                ->where('material_item_id', $mat->id)
                ->whereNull('supplier_id')
                ->where('source', 'system_default');

            if ($base->exists()) {
                $base->update(['emissions_value' => $value, 'updated_at' => now()]);
            } else {
                DB::table('material_item_emissions')->insert([
                    'id'                 => (string) Str::uuid(),
                    'material_item_id'   => $mat->id,
                    'supplier_id'        => null,
                    'emissions_value'    => $value,
                    'source'             => 'system_default',
                    'calculation_method' => 'cradle_to_gate',
                    'reported_period'    => '2025',
                    'is_estimated'       => 1,
                    'is_flagged'         => 0,
                    'reported_at'        => now(),
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }
        }
    }

    /** C2b：7 款轉換成衣的內含碳排改為成衣量級（修掉布料/拉鍊殘值）。 */
    private function fixProductEmissions(): void
    {
        foreach (self::PRODUCT_EMISSIONS as $code => $value) {
            SalesProduct::where('product_code', $code)->update([
                'embedded_emissions'   => $value,
                'emissions_source'     => 'manual',
                'emissions_updated_at' => now(),
            ]);
        }
    }

    /** S1：補齊年採購額（僅填 null，不覆蓋既有值；query builder 不觸發事件，重算由後續統一執行）。 */
    private function assignSpendAmounts(): void
    {
        foreach (self::SPEND_AMOUNTS as $code => $amount) {
            Supplier::where('code', $code)->whereNull('spend_amount')
                ->update(['spend_amount' => $amount]);
        }
    }

    /** R2：舊四軸 CAP 標題改寫為六維語言（冪等：僅匹配舊格式）。 */
    private function rewriteCapTitles(): void
    {
        foreach (CAP::where('title', 'like', '%維度需改善%')->get() as $cap) {
            if (preg_match('/— (GP|E|S|G) 維度需改善/u', $cap->title)) {
                $newTitle = preg_replace_callback(
                    '/— (GP|E|S|G) 維度需改善/u',
                    fn($m) => '— ' . (self::CAP_DIM_MAP[$m[1]] ?? $m[1]) . '維度需改善',
                    $cap->title,
                );
                $cap->update(['title' => $newTitle]);
            }
        }
    }

    // ══════════════ 第三波：ERP 供應鏈硬規則（Tier1–4 全涵蓋） ══════════════

    /** ERP 供應鏈層級主檔（tier 為 ERP 擁有，此處模擬 ERP sync 覆蓋） */
    private const TIER_CASCADE = [
        // T1 成衣縫製（直接供應商）
        'GMN-001' => 1, 'GMN-002' => 1, 'GMN-003' => 1, 'GMN-004' => 1, 'GMN-005' => 1, 'GMN-006' => 1,
        'VLG-001' => 1, 'DGF-001' => 1,
        // T2 織布/染整/輔料
        'WVN-001' => 2, 'WVN-002' => 2, 'WVN-003' => 2, 'DYE-001' => 2, 'DYE-002' => 2,
        'MTM-001' => 2, 'TRM-001' => 2, 'TRM-002' => 2, 'TRM-003' => 2, 'TRM-004' => 2,
        // T3 紗線/纖維/化學品
        'CTN-001' => 3, 'CTN-002' => 3, 'CTN-003' => 3, 'CTN-005' => 3,
        'SYN-001' => 3, 'SYN-002' => 3, 'SYN-003' => 3, 'SYN-004' => 3,
        'CHM-001' => 3, 'CHM-002' => 3, 'CHM-003' => 3, 'CHM-004' => 3,
        // T4 基礎原物料
        'CTN-004' => 4, 'AGR-001' => 4, 'PET-001' => 4, 'RBR-001' => 4, 'PLP-001' => 4,
    ];

    /** 新增 T4 基礎原料供應商 */
    private const T4_SUPPLIERS = [
        'AGR-001' => ['name' => 'Gujarat Organic Cotton Farms', 'country' => 'IN', 'industry' => '原棉種植', 'spend' => 450000],
        'PET-001' => ['name' => 'Formosa PTA Petrochemical',   'country' => 'TW', 'industry' => '石化原料', 'spend' => 1200000],
        'RBR-001' => ['name' => 'Hat Yai Rubber Plantation',   'country' => 'TH', 'industry' => '天然橡膠', 'spend' => 220000],
        'PLP-001' => ['name' => 'Alpine Dissolving Pulp GmbH', 'country' => 'AT', 'industry' => '溶解木漿', 'spend' => 380000],
    ];

    /** 新增 T4 基礎原物料：碼 => [名稱, HS, 所屬群組名, 排放係數 kgCO2e/kg] */
    private const T4_MATERIALS = [
        'RAW-BAS-001' => ['原棉（皮馬棉）',       '52010000', '棉紡原料',     1.8],
        'RAW-BAS-002' => ['精對苯二甲酸（PTA）',  '29173600', '合成纖維原料', 2.2],
        'RAW-BAS-003' => ['天然橡膠乳膠',         '40011000', '天然纖維輔料', 1.5],
        'RAW-BAS-004' => ['溶解級木漿',           '47020000', '天然纖維輔料', 1.2],
    ];

    /** 產品家族 → 各 Tier 缺口補線定義 [material_code, 顯示名, qty, unit, supplier_code, type] */
    private const FAMILY_FILL = [
        'cotton' => [
            1 => ['SVC-CMT-001', '成衣縫製（ERP 工單）',   1,    'pcs', 'GMN-001', 'service'],
            2 => ['RAW-FAB-005', '精梳棉針織布（ERP）',    0.40, 'kg',  'WVN-003', 'material'],
            3 => ['RAW-COT-001', '精梳棉紗 32S（ERP）',    0.45, 'kg',  'CTN-001', 'material'],
            4 => ['RAW-BAS-001', '原棉（皮馬棉）',         0.50, 'kg',  'AGR-001', 'material'],
        ],
        'synthetic' => [
            1 => ['SVC-CMT-001', '成衣縫製（ERP 工單）',   1,    'pcs', 'GMN-001', 'service'],
            2 => ['RAW-FAB-001', '機能針織布（ERP）',      0.35, 'kg',  'WVN-002', 'material'],
            3 => ['RAW-SYN-001', '聚酯纖維 DTY（ERP）',    0.38, 'kg',  'SYN-001', 'material'],
            4 => ['RAW-BAS-002', '石化原料 PTA',           0.42, 'kg',  'PET-001', 'material'],
        ],
        'lyocell' => [
            1 => ['SVC-CMT-001', '成衣縫製（ERP 工單）',   1,    'pcs', 'GMN-005', 'service'],
            2 => ['RAW-FAB-003', '萊賽爾梭織布（ERP）',    0.50, 'kg',  'WVN-001', 'material'],
            3 => ['RAW-NAT-004', '萊賽爾纖維',             0.55, 'kg',  'SYN-002', 'material'],
            4 => ['RAW-BAS-004', '溶解級木漿',             0.60, 'kg',  'PLP-001', 'material'],
        ],
        'rubber' => [
            1 => ['SVC-CMT-001', '成衣縫製（ERP 工單）',   1,    'pcs', 'GMN-001', 'service'],
            2 => ['RAW-FAB-004', '彈性羅紋針織布（ERP）',  0.30, 'kg',  'WVN-002', 'material'],
            3 => ['RAW-SYN-004', '氨綸彈性紗（ERP）',      0.05, 'kg',  'SYN-001', 'material'],
            4 => ['RAW-BAS-003', '天然橡膠乳膠',           0.08, 'kg',  'RBR-001', 'material'],
        ],
    ];

    /** 產品 → 家族 */
    private const PRODUCT_FAMILY = [
        'TEX-001' => 'cotton',    'TEX-003' => 'cotton',    'TEX-005' => 'cotton',   'TEX-008' => 'cotton',
        'GMN-TEE-002' => 'cotton', 'GMN-SWT-008' => 'cotton', 'GMN-SKT-010' => 'cotton',
        'TEX-002' => 'synthetic', 'TEX-004' => 'synthetic', 'TEX-006' => 'synthetic', 'TEX-007' => 'synthetic',
        'GMN-JKT-001' => 'synthetic', 'GMN-PNT-003' => 'synthetic', 'GMN-POL-004' => 'synthetic',
        'GMN-CT-005' => 'synthetic', 'GMN-HOD-009' => 'synthetic',
        'GMN-DRS-006' => 'lyocell',
        'GMN-LEG-007' => 'rubber',
    ];

    /**
     * ERP 硬規則：
     *   1. 依 ERP 主檔重分供應鏈層級（T1 縫製 → T2 織染輔料 → T3 紗線化學 → T4 基礎原料）
     *   2. 每款產品的 BOM 主供應商必須涵蓋 T1–T4，缺口依家族補線
     *   3. 所有 BOM line 與 AVL 標記為 ERP 匯入（erp_line_id / erp_imported / erp_designated）
     */
    private function ensureTierCoverage(): void
    {
        // 1) T4 供應商主檔（模擬 ERP sync 建立）
        foreach (self::T4_SUPPLIERS as $code => $s) {
            Supplier::firstOrCreate(
                ['code' => $code],
                [
                    'name'             => $s['name'],
                    'country_code'     => $s['country'],
                    'industry'         => $s['industry'],
                    'tier'             => 4,
                    'status'           => 'active',
                    'onboarding_stage' => 'active',
                    'spend_amount'     => $s['spend'],
                ],
            );
        }

        // 2) ERP 層級覆蓋（tier 為 ERP 擁有欄位）
        foreach (self::TIER_CASCADE as $code => $tier) {
            Supplier::where('code', $code)->update(['tier' => $tier]);
        }

        // 3) T4 基礎原物料 + 排放係數
        foreach (self::T4_MATERIALS as $code => [$name, $hs, $groupName, $emission]) {
            $groupId = MaterialGroup::where('name', $groupName)->value('id');
            $mat = MaterialItem::updateOrCreate(
                ['item_code' => $code],
                ['name' => $name, 'hs_code' => $hs, 'unit' => 'KG', 'material_group_id' => $groupId,
                 'description' => 'T4 基礎原物料（ERP 匯入）', 'is_active' => true],
            );

            $base = DB::table('material_item_emissions')
                ->where('material_item_id', $mat->id)->whereNull('supplier_id')->where('source', 'system_default');
            if ($base->exists()) {
                $base->update(['emissions_value' => $emission, 'updated_at' => now()]);
            } else {
                DB::table('material_item_emissions')->insert([
                    'id' => (string) Str::uuid(), 'material_item_id' => $mat->id, 'supplier_id' => null,
                    'emissions_value' => $emission, 'source' => 'system_default',
                    'calculation_method' => 'cradle_to_gate', 'reported_period' => '2025',
                    'is_estimated' => 1, 'is_flagged' => 0,
                    'reported_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // 4) 每款產品補齊 T1–T4 缺口
        foreach (SalesProduct::all() as $product) {
            $family = self::PRODUCT_FAMILY[$product->product_code] ?? 'cotton';

            // 現有 BOM 主供應商涵蓋的 tier
            $lineIds = ProductBomLine::where('sales_product_id', $product->id)->pluck('id');
            $primarySupplierIds = BomLineSupplier::whereIn('bom_line_id', $lineIds)
                ->where('role', 'primary')->pluck('supplier_id')->unique();
            $covered = Supplier::whereIn('id', $primarySupplierIds)->pluck('tier')->unique()->all();

            foreach ([1, 2, 3, 4] as $tier) {
                if (in_array($tier, $covered, true)) continue;

                [$matCode, $label, $qty, $unit, $supCode, $type] = self::FAMILY_FILL[$family][$tier];
                $mat = MaterialItem::where('item_code', $matCode)->first();
                $sup = Supplier::where('code', $supCode)->first();
                if (!$sup) continue;

                $bomLine = ProductBomLine::create([
                    'sales_product_id'      => $product->id,
                    'material_item_id'      => $mat?->id,
                    'material_name'         => $label,
                    'hs_code'               => $mat?->hs_code,
                    'material_group_id'     => $mat?->material_group_id,
                    'material_group_source' => 'erp_imported',
                    'linkage_status'        => $mat ? 'linked' : 'unlinked',
                    'bom_line_type'         => $type,
                    'quantity'              => $qty,
                    'unit'                  => $unit,
                    'currency'              => 'USD',
                ]);

                BomLineSupplier::create([
                    'bom_line_id' => $bomLine->id,
                    'supplier_id' => $sup->id,
                    'role'        => 'primary',
                    'source'      => 'erp_designated',
                    'sort_order'  => 0,
                ]);
            }
        }

        // 5) ERP 來源標記：全部 BOM line / AVL 一律視為 ERP 匯入
        foreach (SalesProduct::all() as $product) {
            $n = 1;
            foreach (ProductBomLine::where('sales_product_id', $product->id)->orderBy('created_at')->get() as $line) {
                $line->update([
                    'erp_line_id'           => $line->erp_line_id ?: sprintf('ERP-%s-L%02d', $product->product_code, $n),
                    'material_group_source' => 'erp_imported',
                ]);
                $n++;
            }
        }
        DB::table('bom_line_suppliers')->update(['source' => 'erp_designated']);
    }

    /**
     * 清除舊情境的上游材料批次（BATCH-2025-xxx，未綁定 sales_product）
     * 及其原料溯源與出貨行連結——成衣情境的批次一律為產品級。
     */
    private function purgeLegacyBatches(): void
    {
        $legacyIds = ProductionBatch::withTrashed()->whereNull('sales_product_id')->pluck('id');
        if ($legacyIds->isEmpty()) return;

        DB::table('raw_material_origins')->whereIn('production_batch_id', $legacyIds)->delete();
        DB::table('shipment_line_batches')->whereIn('production_batch_id', $legacyIds)->delete();
        ProductionBatch::withTrashed()->whereIn('id', $legacyIds)->forceDelete();
    }

    /** 各家族 T4 原料的產地事實：[原料碼, 原料名, 國別, 設施, lat, lng, 收穫年, 認證] */
    private const FAMILY_ORIGINS = [
        'cotton' => [
            ['RAW-BAS-001', '原棉（皮馬棉）', 'IN', 'Gujarat Organic Cotton Farms – Saurashtra Plot 12', 22.309425, 72.136230, 2025, 'GOTS-2025-IN-4471'],
        ],
        'synthetic' => [
            ['RAW-BAS-002', '石化原料 PTA', 'TW', 'Formosa PTA Plant – Mailiao Complex', 23.793056, 120.209722, null, 'ISCC-PLUS-2025-TW-118'],
        ],
        'lyocell' => [
            ['RAW-BAS-004', '溶解級木漿', 'AT', 'Alpine Dissolving Pulp – Hallein Mill', 47.683120, 13.089750, 2025, 'FSC-C012345'],
        ],
        'rubber' => [
            ['RAW-BAS-003', '天然橡膠乳膠', 'TH', 'Hat Yai Rubber Plantation – Block A7', 6.996350, 100.47180, 2025, 'FSC-NR-2025-TH-208'],
        ],
    ];

    /**
     * 為每個生產批次補原料溯源（EUDR DDS 地塊座標 / UFLPA 原棉佐證）。
     * 溯源綁批次層級：同款產品不同批次可有不同產地。冪等：先清再建。
     */
    private function seedRawMaterialOrigins(): void
    {
        foreach (SalesProduct::all() as $product) {
            $family  = self::PRODUCT_FAMILY[$product->product_code] ?? 'cotton';
            $origins = self::FAMILY_ORIGINS[$family];
            // 棉質產品同時具 UFLPA 敏感性：丹寧/棉 T 另補一筆美棉替代產地示意（不同批不同產地）
            $altCotton = ['RAW-BAS-001', '原棉（陸地棉）', 'US', 'Organic Cotton Alliance – Lubbock TX Farm 3', 33.577863, -101.855166, 2025, 'USDA-OCP-2025-TX-091'];

            $batches = ProductionBatch::where('sales_product_id', $product->id)
                ->orderBy('production_date')->get();

            foreach ($batches as $idx => $batch) {
                DB::table('raw_material_origins')->where('production_batch_id', $batch->id)->delete();

                foreach ($origins as [$matCode, $matName, $country, $facility, $lat, $lng, $harvest, $cert]) {
                    // 棉家族的偶數批改用美棉產地，展示「同產品不同批不同產地」
                    if ($family === 'cotton' && $idx % 2 === 1) {
                        [$matCode, $matName, $country, $facility, $lat, $lng, $harvest, $cert] = $altCotton;
                    }

                    // 綁定該產品的 T4 原料 BOM 行（指明溯源對象是哪個原料）
                    $mat = MaterialItem::where('item_code', $matCode)->first();
                    $bomLineId = $mat
                        ? ProductBomLine::where('sales_product_id', $product->id)
                            ->where('material_item_id', $mat->id)->value('id')
                        : null;

                    RawMaterialOrigin::create([
                        'production_batch_id' => $batch->id,
                        'bom_line_id'         => $bomLineId,
                        'material_name'       => $matName,
                        'origin_country'      => $country,
                        'facility_name'       => $facility,
                        'gps_lat'             => $lat,
                        'gps_lng'             => $lng,
                        'harvest_year'        => $harvest,
                        'certification_ref'   => $cert,
                    ]);
                }
            }
        }
    }

    /** 出貨行配置：shipment_no => [[product_code, 總量(pcs)], ...] */
    private const SHIPMENT_LINES = [
        // EU 出貨：EUDR 主秀（萊賽爾木漿＋天然橡膠皆屬 EUDR 管制原料）
        'SHIP-202606-001' => [
            ['GMN-DRS-006', 1200],
            ['GMN-LEG-007', 800],
        ],
        // JP 出貨：非 EUDR 市場，丹寧短裙（UFLPA 棉花溯源故事）
        'SHIP-202606-002' => [
            ['GMN-SKT-010', 600],
        ],
    ];

    /**
     * 重建出貨行與批次分配（shipment_line_batches），
     * 使 DDS 草稿能沿 出貨行 → 批次 → 原料溯源 取得完整地塊資料。
     * 冪等：逐出貨單清除舊行與分配後重建；不觸碰 pcf_snapshot 鎖定欄位。
     */
    private function seedShipmentAllocations(): void
    {
        foreach (self::SHIPMENT_LINES as $shipmentNo => $lines) {
            $shipment = Shipment::where('shipment_no', $shipmentNo)->first();
            if (!$shipment) continue;

            // 清除既有行與分配
            $oldLineIds = ShipmentLine::where('shipment_id', $shipment->id)->pluck('id');
            ShipmentLineBatch::whereIn('shipment_line_id', $oldLineIds)->delete();
            ShipmentLine::whereIn('id', $oldLineIds)->delete();

            foreach ($lines as [$productCode, $total]) {
                $product = SalesProduct::where('product_code', $productCode)->first();
                if (!$product) continue;

                $batches = ProductionBatch::where('sales_product_id', $product->id)
                    ->orderBy('production_date')->get();
                if ($batches->isEmpty()) continue;

                // 依批次均分配額，末批吃餘數；加權 PCF = Σ(配額×批次PCF)/總量
                $per = intdiv($total, $batches->count());
                $weightedSum = 0.0;

                $line = ShipmentLine::create([
                    'shipment_id'      => $shipment->id,
                    'sales_product_id' => $product->id,
                    'total_quantity'   => $total,
                    'unit'             => 'pcs',
                ]);

                foreach ($batches as $k => $batch) {
                    $alloc = ($k === $batches->count() - 1) ? $total - $per * $k : $per;
                    ShipmentLineBatch::create([
                        'shipment_line_id'    => $line->id,
                        'production_batch_id' => $batch->id,
                        'allocated_quantity'  => $alloc,
                    ]);
                    $weightedSum += $alloc * (float) ($batch->lot_pcf ?? 0);
                }

                $line->update(['weighted_pcf' => round($weightedSum / max($total, 1), 4)]);
            }
        }
    }

    /**
     * 批號×市場出口合規審查 demo：
     *   EUDR 主秀（洋裝/內搭褲 → EU）、UFLPA 故事（丹寧短裙/棉T → US）、
     *   並製造一筆 fail 案例（洋裝最早批的木漿溯源缺收穫年 → EU 審查 fail）。
     */
    private function seedExportReviews(): void
    {
        $svc = app(\App\Services\ProductionBatch\BatchExportReviewService::class);

        $plan = [
            'GMN-DRS-006' => ['EU'],
            'GMN-LEG-007' => ['EU'],
            'GMN-SKT-010' => ['US', 'EU'],
            'GMN-TEE-002' => ['US'],
        ];

        foreach ($plan as $code => $markets) {
            $product = SalesProduct::where('product_code', $code)->first();
            if (!$product) continue;

            foreach (ProductionBatch::where('sales_product_id', $product->id)->get() as $batch) {
                foreach ($markets as $market) {
                    $svc->review($batch, $market);
                }
            }
        }

        // fail 案例：洋裝最早批的木漿溯源抹除收穫年 → 重審 EU 應 fail
        $dress = SalesProduct::where('product_code', 'GMN-DRS-006')->first();
        if ($dress) {
            $firstBatch = ProductionBatch::where('sales_product_id', $dress->id)
                ->orderBy('production_date')->first();
            if ($firstBatch) {
                RawMaterialOrigin::where('production_batch_id', $firstBatch->id)
                    ->update(['harvest_year' => null]);
                $svc->review($firstBatch->fresh('rawMaterialOrigins'), 'EU');
            }
        }
    }

    /** S1 後同步重算全體 impact（call esgchain-ai），並回填缺 impact 快照的舊 RA（R1）。 */
    private function recomputeImpactAndBackfill(): void
    {
        $service = app(ImpactScoreService::class);
        foreach (Supplier::all(['id']) as $supplier) {
            $score = $service->recomputeForSupplier($supplier->id);
            if ($score !== null) {
                RiskAssessment::where('supplier_id', $supplier->id)
                    ->whereNull('impact_score')
                    ->update(['impact_score' => $score]);
            }
        }
    }
}
