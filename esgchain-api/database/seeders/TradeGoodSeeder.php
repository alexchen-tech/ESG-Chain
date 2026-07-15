<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\SalesProduct;
use App\Models\Supplier;
use App\Models\TradeGoodSupplier;
use App\Models\MaterialGroup;

class TradeGoodSeeder extends Seeder
{
    public function run(): void
    {
        // sales_products / trade_good_suppliers / trade_good_supplier_emissions
        // 已由 DatabaseSeeder 的清理區塊統一 truncate，此處不再重複清空
        // （BuyerProductSeeder/ProductBomLineSeeder 已先建立 8 個 SalesProduct，須保留）

        // 紡織業出口品項：EUDR 適用於木漿基纖維（嫘縈/萊賽爾）與天然橡膠彈性布
        // CBAM 不適用於一般紡織品；特殊情況：鋼製金屬配件（拉鍊/釦環）可能適用
        $goods = [
            // ─── 機能布料 ─────────────────────────────
            [
                'name'               => '機能性吸濕排汗布',
                'product_code'       => 'FAB-DRI-001',
                'hs_code'            => '6006.32',
                'unit'               => 'YD',
                'unit_price'         => 3.20,
                'currency'           => 'USD',
                'description'        => '100% 聚酯纖維針織布，吸濕快乾，運動服用途，密度 180g/m²',
                'is_cbam_applicable' => false,
                'cbam_category'      => null,
                'is_eudr_applicable' => false,
                'embedded_emissions' => null,
                'supplier_codes'     => ['SYN-001', 'WVN-001'],
            ],
            [
                'name'               => '防水透氣外層布（Gore-Tex 規格）',
                'product_code'       => 'FAB-WPB-002',
                'hs_code'            => '5903.90',
                'unit'               => 'YD',
                'unit_price'         => 8.50,
                'currency'           => 'USD',
                'description'        => '尼龍基材三層壓合防水透氣布，耐水壓 ≥20,000mm，透濕度 ≥10,000g/m²/24h',
                'is_cbam_applicable' => false,
                'cbam_category'      => null,
                'is_eudr_applicable' => false,
                'embedded_emissions' => null,
                'supplier_codes'     => ['SYN-002'],
            ],
            [
                'name'               => '萊賽爾（Lyocell）混紡布',
                'product_code'       => 'FAB-LYO-003',
                'hs_code'            => '5516.11',
                'unit'               => 'YD',
                'unit_price'         => 5.80,
                'currency'           => 'EUR',
                'description'        => '70% Lyocell / 30% 棉混紡梭織布，木漿溶劑紡，EUDR 盡調文件必要',
                'is_cbam_applicable' => false,
                'cbam_category'      => null,
                'is_eudr_applicable' => true,
                'embedded_emissions' => null,
                'supplier_codes'     => ['SYN-003'],
            ],
            [
                'name'               => '彈性天然橡膠包芯紗布料',
                'product_code'       => 'FAB-ELT-004',
                'hs_code'            => '6004.10',
                'unit'               => 'YD',
                'unit_price'         => 4.30,
                'currency'           => 'USD',
                'description'        => '天然橡膠芯 + 尼龍包覆彈性針織布，用於泳裝與壓力褲，EUDR 橡膠溯源必要',
                'is_cbam_applicable' => false,
                'cbam_category'      => null,
                'is_eudr_applicable' => true,
                'embedded_emissions' => null,
                'supplier_codes'     => ['WVN-003'],
            ],
            // ─── 成衣 ─────────────────────────────────
            [
                'name'               => '機能運動夾克（成衣）',
                'product_code'       => 'GMN-JKT-001',
                'hs_code'            => '6101.30',
                'unit'               => 'PCS',
                'unit_price'         => 24.50,
                'currency'           => 'USD',
                'description'        => '男款防風防潑水運動夾克，主布料 100% 聚酯，反射條設計',
                'is_cbam_applicable' => false,
                'cbam_category'      => null,
                'is_eudr_applicable' => false,
                'embedded_emissions' => null,
                'supplier_codes'     => ['GMN-001', 'GMN-003'],
            ],
            [
                'name'               => '有機棉 T-Shirt（成衣）',
                'product_code'       => 'GMN-TEE-002',
                'hs_code'            => '6109.10',
                'unit'               => 'PCS',
                'unit_price'         => 7.80,
                'currency'           => 'USD',
                'description'        => 'GOTS 認證有機棉圓領 T-Shirt，180g/m² 單面布，男女通用版',
                'is_cbam_applicable' => false,
                'cbam_category'      => null,
                'is_eudr_applicable' => false,
                'embedded_emissions' => null,
                'supplier_codes'     => ['CTN-003', 'GMN-005'],
            ],
            [
                'name'               => '再生聚酯機能褲（成衣）',
                'product_code'       => 'GMN-PNT-003',
                'hs_code'            => '6103.43',
                'unit'               => 'PCS',
                'unit_price'         => 18.00,
                'currency'           => 'USD',
                'description'        => '90% rPET + 10% Elastane 休閒機能褲，GRS 認證再生聚酯',
                'is_cbam_applicable' => false,
                'cbam_category'      => null,
                'is_eudr_applicable' => false,
                'embedded_emissions' => null,
                'supplier_codes'     => ['SYN-004', 'GMN-002'],
            ],
            // ─── 紗線原料 ─────────────────────────────
            [
                'name'               => '精梳棉紗（40s/2）',
                'product_code'       => 'YRN-CTN-001',
                'hs_code'            => '5205.42',
                'unit'               => 'KG',
                'unit_price'         => 3.65,
                'currency'           => 'USD',
                'description'        => '40 支雙股精梳棉紗，環紡，用於高支針織布，棉花原產地需申報',
                'is_cbam_applicable' => false,
                'cbam_category'      => null,
                'is_eudr_applicable' => false,
                'embedded_emissions' => null,
                'supplier_codes'     => ['CTN-001', 'CTN-002'],
            ],
            [
                'name'               => '再生聚酯紗（75D/72F）',
                'product_code'       => 'YRN-REC-002',
                'hs_code'            => '5402.33',
                'unit'               => 'KG',
                'unit_price'         => 2.10,
                'currency'           => 'USD',
                'description'        => '75 丹尼 72 孔再生聚酯 FDY 紗，GRS 認證，回收寶特瓶製',
                'is_cbam_applicable' => false,
                'cbam_category'      => null,
                'is_eudr_applicable' => false,
                'embedded_emissions' => null,
                'supplier_codes'     => ['SYN-003'],
            ],
            // ─── 輔料配件 ─────────────────────────────
            [
                'name'               => '金屬拉鍊（碳鋼齒）',
                'product_code'       => 'TRM-ZIP-001',
                'hs_code'            => '9607.11',
                'unit'               => 'PCS',
                'unit_price'         => 0.35,
                'currency'           => 'USD',
                'description'        => '5# 碳鋼齒金屬拉鍊，長度 20cm，電鍍黑鎳，用於外套門襟；鋼齒 HS 96.07 非 CBAM 直接管制',
                'is_cbam_applicable' => false,
                'cbam_category'      => null,
                'is_eudr_applicable' => false,
                'embedded_emissions' => null,
                'supplier_codes'     => ['TRM-001', 'TRM-002'],
            ],
        ];

        $materialGroup = MaterialGroup::first();
        $acme  = Customer::where('name', 'Acme GmbH')->first();
        $testG = Customer::where('name', 'Test GmbH')->first();
        $agent = Customer::where('name', 'Sales Agent Ltd')->first();

        $customerMap = [
            'FAB-DRI-001' => $testG?->id,  'FAB-WPB-002' => $testG?->id,
            'FAB-LYO-003' => $testG?->id,  'FAB-ELT-004' => $testG?->id,
            'GMN-JKT-001' => $acme?->id,   'GMN-TEE-002' => $acme?->id,
            'GMN-PNT-003' => $acme?->id,
            'YRN-CTN-001' => $agent?->id,  'YRN-REC-002' => $agent?->id,
            'TRM-ZIP-001' => $agent?->id,
        ];

        foreach ($goods as $data) {
            $supplierCodes = $data['supplier_codes'];
            unset($data['supplier_codes']);
            $data['customer_id'] = $customerMap[$data['product_code']] ?? null;

            $good = SalesProduct::create($data);

            foreach ($supplierCodes as $code) {
                $supplier = Supplier::where('code', $code)->first();
                if ($supplier) {
                    TradeGoodSupplier::create([
                        'trade_good_id'     => $good->id,
                        'supplier_id'       => $supplier->id,
                        'material_group_id' => $materialGroup?->id,
                        'notes'             => null,
                    ]);
                }
            }
        }
    }
}
