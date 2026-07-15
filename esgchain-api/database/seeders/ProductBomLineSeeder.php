<?php

namespace Database\Seeders;

use App\Models\BomLineSupplier;
use App\Models\MaterialGroup;
use App\Models\ProductBomLine;
use App\Models\SalesProduct;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductBomLineSeeder extends Seeder
{
    public function run(): void
    {
        $mg = MaterialGroup::all()->keyBy('name');

        $cotton  = $mg['棉紡原料']     ?? null;
        $syn     = $mg['合成纖維原料'] ?? null;
        $natural = $mg['天然纖維輔料'] ?? null;
        $dye     = $mg['染料化學品']   ?? null;
        $metal   = $mg['金屬配件']     ?? null;
        $svcGmn  = $mg['成衣縫製服務'] ?? null;
        $svcDye  = $mg['染整加工服務'] ?? null;

        // alternate_codes: 同一 BomLine 的替代供應商代碼清單
        $bomData = [
            // ── TEX-001 基本棉 T 恤 ──
            'TEX-001' => [
                [
                    'erp_line_id'      => 'TEX001-BOM-01',
                    'material_name'    => '30支精梳棉紗',
                    'hs_code'          => '52052000',
                    'bom_line_type'    => 'material',
                    'material_group'   => $cotton,
                    'supplier_code'    => 'CTN-001',
                    'alternate_codes'  => ['CTN-004'],   // India Cotton Consortium
                    'quantity'         => 1.2, 'unit' => 'kg', 'unit_price' => 3.50, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX001-BOM-02',
                    'material_name'    => '單面平紋針織布（棉100%）',
                    'hs_code'          => '60024000',
                    'bom_line_type'    => 'material',
                    'material_group'   => $cotton,
                    'supplier_code'    => 'WVN-001',
                    'alternate_codes'  => ['WVN-002'],   // Vietnam Weaving Factory
                    'quantity'         => 0.35, 'unit' => 'kg', 'unit_price' => 5.80, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX001-BOM-03',
                    'material_name'    => '環保棉縫線',
                    'hs_code'          => '52041100',
                    'bom_line_type'    => 'material',
                    'material_group'   => $cotton,
                    'supplier_code'    => 'CTN-003',
                    'alternate_codes'  => [],
                    'quantity'         => 0.02, 'unit' => 'kg', 'unit_price' => 8.20, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX001-BOM-04',
                    'material_name'    => '活性染料（黑色 RE-B）',
                    'hs_code'          => '32041390',
                    'bom_line_type'    => 'material',
                    'material_group'   => $dye,
                    'supplier_code'    => 'CHM-001',
                    'alternate_codes'  => ['CHM-002'],   // Archroma Switzerland
                    'quantity'         => 0.03, 'unit' => 'kg', 'unit_price' => 18.00, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX001-BOM-05',
                    'material_name'    => '成衣縫製加工（棉 T 恤）',
                    'hs_code'          => '61091000',
                    'bom_line_type'    => 'service',
                    'material_group'   => $svcGmn,
                    'supplier_code'    => 'GMN-001',
                    'alternate_codes'  => ['GMN-002', 'GMN-003'],  // DBL Bangladesh, Phatex Cambodia
                    'quantity'         => 1.0, 'unit' => 'pcs', 'unit_price' => 2.50, 'currency' => 'USD',
                ],
            ],

            // ── TEX-002 機能運動長褲 ──
            'TEX-002' => [
                [
                    'erp_line_id'      => 'TEX002-BOM-01',
                    'material_name'    => '85/15 聚酯/彈性纖維混紡絲',
                    'hs_code'          => '54024900',
                    'bom_line_type'    => 'material',
                    'material_group'   => $syn,
                    'supplier_code'    => 'SYN-001',
                    'alternate_codes'  => ['SYN-003'],   // Far Eastern New Century Vietnam
                    'quantity'         => 0.80, 'unit' => 'kg', 'unit_price' => 4.20, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX002-BOM-02',
                    'material_name'    => '機能彈性針織布（四面彈）',
                    'hs_code'          => '60041000',
                    'bom_line_type'    => 'material',
                    'material_group'   => $syn,
                    'supplier_code'    => 'WVN-001',
                    'alternate_codes'  => ['WVN-002'],   // Vietnam Weaving Factory
                    'quantity'         => 0.42, 'unit' => 'kg', 'unit_price' => 9.50, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX002-BOM-03',
                    'material_name'    => '5cm 彈性腰帶（尼龍）',
                    'hs_code'          => '58062000',
                    'bom_line_type'    => 'material',
                    'material_group'   => $syn,
                    'supplier_code'    => 'TRM-003',
                    'alternate_codes'  => [],
                    'quantity'         => 0.08, 'unit' => 'm', 'unit_price' => 0.65, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX002-BOM-04',
                    'material_name'    => '分散染料（海軍藍 SE-2R）',
                    'hs_code'          => '32041100',
                    'bom_line_type'    => 'material',
                    'material_group'   => $dye,
                    'supplier_code'    => 'CHM-002',
                    'alternate_codes'  => ['CHM-004'],   // Kiri Industries India
                    'quantity'         => 0.025, 'unit' => 'kg', 'unit_price' => 22.00, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX002-BOM-05',
                    'material_name'    => 'DWR 防水整理劑（氟素free）',
                    'hs_code'          => '38091000',
                    'bom_line_type'    => 'material',
                    'material_group'   => $dye,
                    'supplier_code'    => 'CHM-003',
                    'alternate_codes'  => ['CHM-001'],   // Huntsman Textile Effects
                    'quantity'         => 0.015, 'unit' => 'kg', 'unit_price' => 35.00, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX002-BOM-06',
                    'material_name'    => '染整加工服務（運動長褲）',
                    'hs_code'          => '62034900',
                    'bom_line_type'    => 'service',
                    'material_group'   => $svcDye,
                    'supplier_code'    => 'DYE-001',
                    'alternate_codes'  => ['DYE-002'],   // PT Sari Warna Indonesia
                    'quantity'         => 1.0, 'unit' => 'pcs', 'unit_price' => 1.80, 'currency' => 'USD',
                ],
            ],

            // ── TEX-003 石洗牛仔外套 ──
            'TEX-003' => [
                [
                    'erp_line_id'      => 'TEX003-BOM-01',
                    'material_name'    => '10oz 牛仔布（棉100%）',
                    'hs_code'          => '52094200',
                    'bom_line_type'    => 'material',
                    'material_group'   => $cotton,
                    'supplier_code'    => 'CTN-002',
                    'alternate_codes'  => ['CTN-001', 'WVN-003'],  // 台灣紡紗, Bangladesh Knitting
                    'quantity'         => 1.50, 'unit' => 'kg', 'unit_price' => 4.80, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX003-BOM-02',
                    'material_name'    => '棉質裡布（平紋）',
                    'hs_code'          => '52081100',
                    'bom_line_type'    => 'material',
                    'material_group'   => $cotton,
                    'supplier_code'    => 'WVN-003',
                    'alternate_codes'  => ['WVN-001'],   // 宏遠興業
                    'quantity'         => 0.30, 'unit' => 'kg', 'unit_price' => 3.20, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX003-BOM-03',
                    'material_name'    => 'YKK 5# 銅拉鍊（25cm）',
                    'hs_code'          => '96071900',
                    'bom_line_type'    => 'material',
                    'material_group'   => $metal,
                    'supplier_code'    => 'TRM-001',
                    'alternate_codes'  => ['TRM-002'],   // 浙江偉星
                    'quantity'         => 3.0, 'unit' => 'pcs', 'unit_price' => 0.85, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX003-BOM-04',
                    'material_name'    => '銅鉚釘（∅12mm）',
                    'hs_code'          => '83081000',
                    'bom_line_type'    => 'material',
                    'material_group'   => $metal,
                    'supplier_code'    => 'TRM-002',
                    'alternate_codes'  => [],
                    'quantity'         => 8.0, 'unit' => 'pcs', 'unit_price' => 0.12, 'currency' => 'USD',
                ],
            ],

            // ── TEX-004 輕量羽絨背心 ──
            'TEX-004' => [
                [
                    'erp_line_id'      => 'TEX004-BOM-01',
                    'material_name'    => '防水尼龍外層布（40D）',
                    'hs_code'          => '54022000',
                    'bom_line_type'    => 'material',
                    'material_group'   => $syn,
                    'supplier_code'    => 'SYN-002',
                    'alternate_codes'  => ['SYN-001'],
                    'quantity'         => 0.35, 'unit' => 'kg', 'unit_price' => 10.50, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX004-BOM-02',
                    'material_name'    => '天然羽絨填充（鴨絨 90/10）',
                    'hs_code'          => '05051000',
                    'bom_line_type'    => 'material',
                    'material_group'   => $natural,
                    'supplier_code'    => 'CTN-004',
                    'alternate_codes'  => [],
                    'quantity'         => 0.12, 'unit' => 'kg', 'unit_price' => 45.00, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX004-BOM-03',
                    'material_name'    => 'YKK 防水輕量拉鍊（50cm）',
                    'hs_code'          => '96071100',
                    'bom_line_type'    => 'material',
                    'material_group'   => $metal,
                    'supplier_code'    => 'TRM-001',
                    'alternate_codes'  => [],
                    'quantity'         => 3.0, 'unit' => 'pcs', 'unit_price' => 1.80, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX004-BOM-04',
                    'material_name'    => '木質飾扣（EUDR 認證）',
                    'hs_code'          => '96062100',
                    'bom_line_type'    => 'material',
                    'material_group'   => $metal,
                    'supplier_code'    => 'TRM-002',
                    'alternate_codes'  => [],
                    'quantity'         => 4.0, 'unit' => 'pcs', 'unit_price' => 0.60, 'currency' => 'USD',
                    'source'           => 'manual',
                ],
                [
                    'erp_line_id'      => 'TEX004-BOM-05',
                    'material_name'    => '功能性外套縫製服務',
                    'hs_code'          => '62013000',
                    'bom_line_type'    => 'service',
                    'material_group'   => $svcGmn,
                    'supplier_code'    => 'GMN-003',
                    'alternate_codes'  => ['GMN-001'],
                    'quantity'         => 1.0, 'unit' => 'pcs', 'unit_price' => 4.50, 'currency' => 'USD',
                ],
            ],

            // ── TEX-005 兒童環保 T 恤（有機棉 + UFLPA 溯源）──
            'TEX-005' => [
                [
                    'erp_line_id'      => 'TEX005-BOM-01',
                    'material_name'    => '有機棉紗 40S（GOTS 認證）',
                    'hs_code'          => '52051200',
                    'bom_line_type'    => 'material',
                    'material_group'   => $cotton,
                    'supplier_code'    => 'CTN-003',
                    'alternate_codes'  => ['CTN-001'],
                    'quantity'         => 0.90, 'unit' => 'kg', 'unit_price' => 4.80, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX005-BOM-02',
                    'material_name'    => '有機棉平紋針織布（120gsm）',
                    'hs_code'          => '60024000',
                    'bom_line_type'    => 'material',
                    'material_group'   => $cotton,
                    'supplier_code'    => 'WVN-001',
                    'alternate_codes'  => ['WVN-002'],
                    'quantity'         => 0.28, 'unit' => 'kg', 'unit_price' => 6.20, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX005-BOM-03',
                    'material_name'    => '活性染料（安全認證，無 SVHC）',
                    'hs_code'          => '32041110',
                    'bom_line_type'    => 'material',
                    'material_group'   => $dye,
                    'supplier_code'    => 'CHM-001',
                    'alternate_codes'  => [],
                    'quantity'         => 0.02, 'unit' => 'kg', 'unit_price' => 20.00, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX005-BOM-04',
                    'material_name'    => 'T恤 CMT 縫製服務（小童尺碼）',
                    'hs_code'          => '61091000',
                    'bom_line_type'    => 'service',
                    'material_group'   => $svcGmn,
                    'supplier_code'    => 'GMN-001',
                    'alternate_codes'  => ['GMN-002'],
                    'quantity'         => 1.0, 'unit' => 'pcs', 'unit_price' => 2.20, 'currency' => 'USD',
                ],
            ],

            // ── TEX-006 快乾機能衫（rPET 再生聚酯）──
            'TEX-006' => [
                [
                    'erp_line_id'      => 'TEX006-BOM-01',
                    'material_name'    => 'rPET 再生聚酯纖維 75D（GRS 認證）',
                    'hs_code'          => '54023300',
                    'bom_line_type'    => 'material',
                    'material_group'   => $syn,
                    'supplier_code'    => 'SYN-003',
                    'alternate_codes'  => ['SYN-001'],
                    'quantity'         => 0.75, 'unit' => 'kg', 'unit_price' => 3.80, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX006-BOM-02',
                    'material_name'    => 'rPET 快乾針織布（單面）',
                    'hs_code'          => '60041000',
                    'bom_line_type'    => 'material',
                    'material_group'   => $syn,
                    'supplier_code'    => 'WVN-001',
                    'alternate_codes'  => [],
                    'quantity'         => 0.38, 'unit' => 'kg', 'unit_price' => 7.50, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX006-BOM-03',
                    'material_name'    => '防污塗層整理服務（DWR, PFC-free）',
                    'hs_code'          => '38091000',
                    'bom_line_type'    => 'service',
                    'material_group'   => $svcDye,
                    'supplier_code'    => 'DYE-001',
                    'alternate_codes'  => ['CHM-003'],
                    'quantity'         => 1.0, 'unit' => 'pcs', 'unit_price' => 0.80, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX006-BOM-04',
                    'material_name'    => 'T恤 CMT 縫製服務（機能衫）',
                    'hs_code'          => '61091000',
                    'bom_line_type'    => 'service',
                    'material_group'   => $svcGmn,
                    'supplier_code'    => 'GMN-002',
                    'alternate_codes'  => ['GMN-001'],
                    'quantity'         => 1.0, 'unit' => 'pcs', 'unit_price' => 2.40, 'currency' => 'USD',
                ],
            ],

            // ── TEX-008 男性正裝棉麻襯衫 ──
            'TEX-008' => [
                [
                    'erp_line_id'      => 'TEX008-BOM-01',
                    'material_name'    => '棉麻混紡梭織布（60/40, 150cm幅）',
                    'hs_code'          => '52081100',
                    'bom_line_type'    => 'material',
                    'material_group'   => $cotton,
                    'supplier_code'    => 'WVN-003',
                    'alternate_codes'  => ['WVN-001'],
                    'quantity'         => 0.55, 'unit' => 'kg', 'unit_price' => 6.80, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX008-BOM-02',
                    'material_name'    => '精梳棉紗縫線（60/2）',
                    'hs_code'          => '52051100',
                    'bom_line_type'    => 'material',
                    'material_group'   => $cotton,
                    'supplier_code'    => 'CTN-001',
                    'alternate_codes'  => [],
                    'quantity'         => 0.015, 'unit' => 'kg', 'unit_price' => 9.00, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX008-BOM-03',
                    'material_name'    => '不銹鋼貝殼鈕扣（∅13mm）',
                    'hs_code'          => '96062200',
                    'bom_line_type'    => 'material',
                    'material_group'   => $metal,
                    'supplier_code'    => 'TRM-002',
                    'alternate_codes'  => [],
                    'quantity'         => 8.0, 'unit' => 'pcs', 'unit_price' => 0.08, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX008-BOM-04',
                    'material_name'    => '活性染料（藍色系，OEKO-TEX）',
                    'hs_code'          => '32041110',
                    'bom_line_type'    => 'material',
                    'material_group'   => $dye,
                    'supplier_code'    => 'CHM-001',
                    'alternate_codes'  => ['CHM-002'],
                    'quantity'         => 0.025, 'unit' => 'kg', 'unit_price' => 18.00, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX008-BOM-05',
                    'material_name'    => '正裝襯衫 CMT 縫製服務',
                    'hs_code'          => '62052000',
                    'bom_line_type'    => 'service',
                    'material_group'   => $svcGmn,
                    'supplier_code'    => 'GMN-001',
                    'alternate_codes'  => ['GMN-003'],
                    'quantity'         => 1.0, 'unit' => 'pcs', 'unit_price' => 3.20, 'currency' => 'USD',
                ],
            ],

            // ── TEX-007 戶外機能夾克 ──
            'TEX-007' => [
                [
                    'erp_line_id'      => 'TEX007-BOM-01',
                    'material_name'    => '30D 尼龍塔絲隆布面層',
                    'hs_code'          => '54022000',
                    'bom_line_type'    => 'material',
                    'material_group'   => $syn,
                    'supplier_code'    => 'SYN-002',
                    'alternate_codes'  => ['SYN-003', 'SYN-004'],  // Far Eastern Vietnam, Indorama
                    'quantity'         => 0.60, 'unit' => 'kg', 'unit_price' => 12.00, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX007-BOM-02',
                    'material_name'    => '透濕防水 TPU 膜',
                    'hs_code'          => '54071000',
                    'bom_line_type'    => 'material',
                    'material_group'   => $syn,
                    'supplier_code'    => 'WVN-001',
                    'alternate_codes'  => ['SYN-001'],   // 南亞塑膠
                    'quantity'         => 0.20, 'unit' => 'kg', 'unit_price' => 18.00, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX007-BOM-03',
                    'material_name'    => 'YKK Aquaguard 防水拉鍊（60cm）',
                    'hs_code'          => '96071100',
                    'bom_line_type'    => 'material',
                    'material_group'   => $metal,
                    'supplier_code'    => 'TRM-001',
                    'alternate_codes'  => [],
                    'quantity'         => 2.0, 'unit' => 'pcs', 'unit_price' => 2.40, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX007-BOM-04',
                    'material_name'    => 'C0 DWR 防水整理劑',
                    'hs_code'          => '38091000',
                    'bom_line_type'    => 'material',
                    'material_group'   => $dye,
                    'supplier_code'    => 'CHM-001',
                    'alternate_codes'  => ['CHM-003'],   // 台灣恆隆化學
                    'quantity'         => 0.02, 'unit' => 'kg', 'unit_price' => 42.00, 'currency' => 'USD',
                ],
                [
                    'erp_line_id'      => 'TEX007-BOM-05',
                    'material_name'    => '尼龍調節扣（鋁合金）',
                    'hs_code'          => '76169900',
                    'bom_line_type'    => 'material',
                    'material_group'   => $metal,
                    'supplier_code'    => 'TRM-004',
                    'alternate_codes'  => ['TRM-002'],   // 浙江偉星
                    'quantity'         => 4.0, 'unit' => 'pcs', 'unit_price' => 0.55, 'currency' => 'USD',
                    'source'           => 'manual',
                ],
            ],
        ];

        foreach ($bomData as $productCode => $lines) {
            $product = SalesProduct::where('product_code', $productCode)->first();
            if (!$product) continue;

            foreach ($lines as $line) {
                $supplierCode   = $line['supplier_code'];
                $alternateCodes = $line['alternate_codes'] ?? [];
                $materialGroup  = $line['material_group'];
                $source         = $line['source'] ?? 'erp_imported';
                unset($line['supplier_code'], $line['alternate_codes'], $line['material_group'], $line['source']);

                $supplier = Supplier::where('code', $supplierCode)->first();

                $mgSource = $source === 'manual' ? 'manual' : 'erp_imported';

                $bomLine = ProductBomLine::firstOrCreate(
                    [
                        'sales_product_id' => $product->id,
                        'erp_line_id'      => $line['erp_line_id'],
                    ],
                    array_merge($line, [
                        'sales_product_id'      => $product->id,
                        'material_group_id'     => $materialGroup?->id,
                        'material_group_source' => $materialGroup ? $mgSource : null,
                    ])
                );

                $bsSource = $source === 'manual' ? 'manual' : 'erp_designated';

                // 建立主要供應商
                if ($supplier) {
                    BomLineSupplier::firstOrCreate(
                        ['bom_line_id' => $bomLine->id, 'supplier_id' => $supplier->id],
                        ['role' => 'primary', 'source' => $bsSource, 'sort_order' => 0]
                    );
                }

                // 建立替代供應商
                foreach ($alternateCodes as $i => $altCode) {
                    $altSupplier = Supplier::where('code', $altCode)->first();
                    if ($altSupplier) {
                        BomLineSupplier::firstOrCreate(
                            ['bom_line_id' => $bomLine->id, 'supplier_id' => $altSupplier->id],
                            ['role' => 'alternate', 'source' => $bsSource, 'sort_order' => $i + 1]
                        );
                    }
                }
            }
        }
    }
}
