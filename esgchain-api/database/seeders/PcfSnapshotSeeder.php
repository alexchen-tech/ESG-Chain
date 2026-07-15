<?php

namespace Database\Seeders;

use App\Models\SalesProduct;
use App\Models\MaterialItem;
use App\Models\MaterialItemEmission;
use App\Models\ProductBomLine;
use App\Models\Supplier;
use App\Services\PCF\PcfCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * 植入 PCF 快照示範資料：
 * 1. 連結 BOM 行 → 物料主檔（material_item_id）
 * 2. 建立供應商碳排記錄（混合 portal-self / buyer-input / ai-estimated）
 * 3. 呼叫 PcfCalculationService 計算並寫入快照
 */
class PcfSnapshotSeeder extends Seeder
{
    public function run(PcfCalculationService $pcfService): void
    {
        // ── 1. BOM 行 → 物料主檔 對照表（erp_line_id => item_code）──
        $bomToItem = [
            'TEX001-BOM-01' => 'RAW-COT-001',  // 棉紗
            'TEX001-BOM-02' => 'RAW-COT-003',  // 棉布
            'TEX001-BOM-03' => 'RAW-COT-002',  // 縫線 → 有機棉紗 as proxy
            'TEX001-BOM-04' => 'CHM-DYE-001',  // 活性染料
            'TEX001-BOM-05' => 'SVC-CMT-001',  // 縫製服務

            'TEX002-BOM-01' => 'RAW-SYN-001',  // 聚酯纖維
            'TEX002-BOM-02' => 'RAW-SYN-004',  // 彈性布 → 氨綸
            'TEX002-BOM-03' => 'RAW-SYN-002',  // 尼龍腰帶
            'TEX002-BOM-04' => 'CHM-DYE-002',  // 分散染料
            'TEX002-BOM-05' => 'CHM-DYE-003',  // DWR → 固色劑 as proxy
            'TEX002-BOM-06' => 'SVC-DYE-001',  // 染整服務

            'TEX003-BOM-01' => 'RAW-COT-003',  // 牛仔布
            'TEX003-BOM-02' => 'RAW-COT-003',  // 棉裡布
            'TEX003-BOM-03' => 'ACC-MET-001',  // 銅拉鍊頭
            'TEX003-BOM-04' => 'ACC-MET-004',  // 銅鉚釘

            'TEX007-BOM-01' => 'RAW-SYN-002',  // 尼龍外層布
            'TEX007-BOM-02' => 'RAW-SYN-001',  // TPU 膜 → 聚酯 as proxy
            'TEX007-BOM-03' => 'ACC-MET-001',  // 防水拉鍊頭
            'TEX007-BOM-04' => 'CHM-DYE-003',  // DWR 整理劑
            'TEX007-BOM-05' => 'ACC-MET-003',  // 鋁合金調節扣

            // TEX-004 輕量羽絨背心
            'TEX004-BOM-01' => 'RAW-SYN-002',  // 防水尼龍外層布 → 尼龍紗
            'TEX004-BOM-02' => 'RAW-NAT-001',  // 天然羽絨填充 → 羊毛 as proxy
            'TEX004-BOM-03' => 'ACC-MET-001',  // YKK 防水拉鍊
            'TEX004-BOM-04' => 'ACC-MET-004',  // 木質飾扣 → 銅鉚釘 as proxy
            'TEX004-BOM-05' => 'SVC-CMT-003',  // 功能性外套縫製服務

            // TEX-005 兒童環保 T 恤
            'TEX005-BOM-01' => 'RAW-COT-002',  // 有機棉紗 40S
            'TEX005-BOM-02' => 'RAW-COT-003',  // 有機棉針織布 → 棉布坯布
            'TEX005-BOM-03' => 'CHM-DYE-001',  // 活性染料
            'TEX005-BOM-04' => 'SVC-CMT-001',  // CMT 縫製服務

            // TEX-006 快乾機能衫（rPET）
            'TEX006-BOM-01' => 'RAW-SYN-003',  // rPET 再生聚酯纖維
            'TEX006-BOM-02' => 'RAW-SYN-001',  // rPET 針織布 → 聚酯 as proxy
            'TEX006-BOM-03' => 'SVC-DYE-001',  // DWR 整理 → 染整加工服務
            'TEX006-BOM-04' => 'SVC-CMT-001',  // CMT 縫製服務

            // TEX-008 男性正裝棉麻襯衫
            'TEX008-BOM-01' => 'RAW-COT-003',  // 棉麻混紡布 → 棉布坯布 as proxy
            'TEX008-BOM-02' => 'RAW-COT-001',  // 棉紗縫線 → 精梳棉紗
            'TEX008-BOM-03' => 'ACC-MET-002',  // 不銹鋼鈕扣 → 不銹鋼四合扣
            'TEX008-BOM-04' => 'CHM-DYE-001',  // 活性染料
            'TEX008-BOM-05' => 'SVC-CMT-001',  // CMT 縫製服務
        ];

        // 預先撈出 item_code → id
        $itemIds = MaterialItem::pluck('id', 'item_code');

        // ── 2. 更新 BOM 行的 material_item_id ──
        foreach ($bomToItem as $erpLineId => $itemCode) {
            $itemId = $itemIds[$itemCode] ?? null;
            if (!$itemId) continue;

            ProductBomLine::where('erp_line_id', $erpLineId)
                ->whereNull('material_item_id')
                ->update(['material_item_id' => $itemId]);
        }

        // ── 3. 供應商碳排記錄（supplier_code × item_code × 數值/來源）──
        //    混合三種來源展示不同資料品質
        $emissionRecords = [
            // TEX-001 基本棉T恤 ─ 主要供應商皆已提報，iso14067_ready 接近達標
            ['supplier' => 'CTN-001', 'item' => 'RAW-COT-001', 'value' => 5.48, 'source' => 'portal-self',  'period' => '2024-Q3', 'method' => 'cradle_to_gate', 'estimated' => false],
            ['supplier' => 'WVN-001', 'item' => 'RAW-COT-003', 'value' => 6.20, 'source' => 'buyer-input',  'period' => '2024',    'method' => 'cradle_to_gate', 'estimated' => false],
            ['supplier' => 'CTN-003', 'item' => 'RAW-COT-002', 'value' => 4.10, 'source' => 'ai-estimated', 'period' => null,      'method' => null,             'estimated' => true],
            ['supplier' => 'CHM-001', 'item' => 'CHM-DYE-001', 'value' => 11.80,'source' => 'portal-self',  'period' => '2024-Q2', 'method' => 'cradle_to_gate', 'estimated' => false],
            ['supplier' => 'GMN-001', 'item' => 'SVC-CMT-001', 'value' => 0.75, 'source' => 'portal-self',  'period' => '2024-Q3', 'method' => 'process_based',  'estimated' => false],

            // TEX-002 機能運動長褲 ─ 合纖供應商部分提報，染整尚未提報
            ['supplier' => 'SYN-001', 'item' => 'RAW-SYN-001', 'value' => 9.20, 'source' => 'portal-self',  'period' => '2024-Q1', 'method' => 'cradle_to_gate', 'estimated' => false],
            ['supplier' => 'WVN-001', 'item' => 'RAW-SYN-004', 'value' => 15.60,'source' => 'ai-estimated', 'period' => null,      'method' => null,             'estimated' => true],
            ['supplier' => 'TRM-003', 'item' => 'RAW-SYN-002', 'value' => 7.60, 'source' => 'ai-estimated', 'period' => null,      'method' => null,             'estimated' => true],
            ['supplier' => 'CHM-002', 'item' => 'CHM-DYE-002', 'value' => 13.90,'source' => 'buyer-input',  'period' => '2024',    'method' => 'cradle_to_gate', 'estimated' => false],
            ['supplier' => 'CHM-003', 'item' => 'CHM-DYE-003', 'value' => 6.40, 'source' => 'ai-estimated', 'period' => null,      'method' => null,             'estimated' => true],
            ['supplier' => 'DYE-001', 'item' => 'SVC-DYE-001', 'value' => 3.95, 'source' => 'portal-self',  'period' => '2024-Q2', 'method' => 'process_based',  'estimated' => false],

            // TEX-003 石洗牛仔外套 ─ 金屬供應商 TRM-001 已提報
            ['supplier' => 'CTN-002', 'item' => 'RAW-COT-003', 'value' => 6.85, 'source' => 'buyer-input',  'period' => '2024',    'method' => 'cradle_to_gate', 'estimated' => false],
            ['supplier' => 'WVN-003', 'item' => 'RAW-COT-003', 'value' => 6.20, 'source' => 'ai-estimated', 'period' => null,      'method' => null,             'estimated' => true],
            ['supplier' => 'TRM-001', 'item' => 'ACC-MET-001', 'value' => 0.011,'source' => 'portal-self',  'period' => '2024-Q3', 'method' => 'cradle_to_gate', 'estimated' => false],
            ['supplier' => 'TRM-002', 'item' => 'ACC-MET-004', 'value' => 0.003,'source' => 'ai-estimated', 'period' => null,      'method' => null,             'estimated' => true],

            // TEX-004 輕量羽絨背心 ─ 羽絨為高碳排物料，合纖主要 ai-estimated
            ['supplier' => 'SYN-002', 'item' => 'RAW-SYN-002', 'value' => 8.20, 'source' => 'ai-estimated', 'period' => null,      'method' => null,             'estimated' => true],
            ['supplier' => 'CTN-004', 'item' => 'RAW-NAT-001',  'value' => 22.50,'source' => 'ai-estimated', 'period' => null,      'method' => null,             'estimated' => true],
            ['supplier' => 'GMN-003', 'item' => 'SVC-CMT-003',  'value' => 1.10, 'source' => 'portal-self',  'period' => '2024-Q3', 'method' => 'process_based',  'estimated' => false],

            // TEX-005 兒童環保 T 恤 ─ GOTS 有機棉，CTN-003 已提報，其他共用記錄
            // （CTN-003×RAW-COT-002, WVN-001×RAW-COT-003, CHM-001×CHM-DYE-001, GMN-001×SVC-CMT-001 已在 TEX-001 建立）

            // TEX-006 快乾機能衫 ─ rPET 核心物料含 GRS 認證
            ['supplier' => 'SYN-003', 'item' => 'RAW-SYN-003', 'value' => 5.60, 'source' => 'buyer-input',  'period' => '2024',    'method' => 'cradle_to_gate', 'estimated' => false],
            ['supplier' => 'GMN-002', 'item' => 'SVC-CMT-001',  'value' => 0.90, 'source' => 'ai-estimated', 'period' => null,      'method' => null,             'estimated' => true],

            // TEX-008 棉麻正裝襯衫 ─ 棉麻混紡，TRM-002 鈕扣 ai-estimated
            ['supplier' => 'TRM-002', 'item' => 'ACC-MET-002', 'value' => 0.005,'source' => 'ai-estimated', 'period' => null,      'method' => null,             'estimated' => true],

            // TEX-007 戶外機能夾克 ─ 多數 ai-estimated，展示低資料品質狀態
            ['supplier' => 'SYN-002', 'item' => 'RAW-SYN-002', 'value' => 8.20, 'source' => 'ai-estimated', 'period' => null,      'method' => null,             'estimated' => true],
            ['supplier' => 'WVN-001', 'item' => 'RAW-SYN-001', 'value' => 9.00, 'source' => 'ai-estimated', 'period' => null,      'method' => null,             'estimated' => true],
            ['supplier' => 'TRM-001', 'item' => 'ACC-MET-001', 'value' => 0.011,'source' => 'portal-self',  'period' => '2024-Q3', 'method' => 'cradle_to_gate', 'estimated' => false],
            ['supplier' => 'CHM-001', 'item' => 'CHM-DYE-003', 'value' => 6.80, 'source' => 'ai-estimated', 'period' => null,      'method' => null,             'estimated' => true],
            ['supplier' => 'TRM-004', 'item' => 'ACC-MET-003', 'value' => 0.018,'source' => 'ai-estimated', 'period' => null,      'method' => null,             'estimated' => true],
        ];

        $supplierIds = Supplier::pluck('id', 'code');
        $reportedAt  = Carbon::now()->subDays(7);

        foreach ($emissionRecords as $rec) {
            $supplierId = $supplierIds[$rec['supplier']] ?? null;
            $itemId     = $itemIds[$rec['item']] ?? null;
            if (!$supplierId || !$itemId) continue;

            $exists = MaterialItemEmission::where('material_item_id', $itemId)
                ->where('supplier_id', $supplierId)
                ->where('source', $rec['source'])
                ->exists();

            if (!$exists) {
                // withoutEvents: 避免觸發 Observer 在 Seeder 期間觸發 PCF 重算
                MaterialItemEmission::withoutEvents(function () use ($itemId, $supplierId, $rec, $reportedAt) {
                    MaterialItemEmission::create([
                        'material_item_id'   => $itemId,
                        'supplier_id'        => $supplierId,
                        'emissions_value'    => $rec['value'],
                        'source'             => $rec['source'],
                        'calculation_method' => $rec['method'],
                        'reported_period'    => $rec['period'],
                        'is_estimated'       => $rec['estimated'],
                        'is_flagged'         => false,
                        'reported_at'        => $reportedAt,
                    ]);
                });
            }
        }

        // ── 4. 計算並寫入 PCF 快照 ──
        $productCodes = ['TEX-001', 'TEX-002', 'TEX-003', 'TEX-004', 'TEX-005', 'TEX-006', 'TEX-007', 'TEX-008'];
        foreach ($productCodes as $code) {
            $product = SalesProduct::where('product_code', $code)->first();
            if (!$product) continue;

            // 避免重複建立
            if ($product->pcfSnapshots()->exists()) continue;

            try {
                $snapshot = $pcfService->snapshot($product);
                $this->command->info("PCF 快照建立：{$code} → {$snapshot->total_pcf} kgCO₂e/件 (ISO 14067 ready: " . ($snapshot->iso14067_ready ? 'Y' : 'N') . ')');
            } catch (\Throwable $e) {
                $this->command->warn("PCF 計算失敗 {$code}：{$e->getMessage()}");
            }
        }
    }
}
