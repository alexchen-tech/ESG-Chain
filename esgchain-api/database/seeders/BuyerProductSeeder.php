<?php

namespace Database\Seeders;

use App\Models\MaterialGroup;
use App\Models\SalesProduct;
use Illuminate\Database\Seeder;

class BuyerProductSeeder extends Seeder
{
    public function run(): void
    {
        $mg = MaterialGroup::all()->keyBy('name');

        $cotton  = $mg['棉紡原料']     ?? null;
        $syn     = $mg['合成纖維原料'] ?? null;
        $natural = $mg['天然纖維輔料'] ?? null;
        $dye     = $mg['染料化學品']   ?? null;
        $metal   = $mg['金屬配件']     ?? null;

        // 採購商視角：台灣戶外機能服飾品牌
        $products = [
            [
                'name'         => '基本棉 T 恤',
                'product_code' => 'TEX-001',
                'hs_code'      => '6109.10',
                'description'  => '100% 棉製日常基本款 T 恤，主銷歐美市場，受 UFLPA 新疆棉管控',
                'suppliers' => [
                    ['code' => 'CTN-001', 'group' => $cotton],
                    ['code' => 'GMN-001', 'group' => $cotton],
                    ['code' => 'GMN-005', 'group' => $cotton],
                ],
            ],
            [
                'name'         => '機能運動長褲',
                'product_code' => 'TEX-002',
                'hs_code'      => '6103.43',
                'description'  => '85% 聚酯 / 15% 彈性纖維機能運動褲，REACH SVHC 管制化學品申報',
                'suppliers' => [
                    ['code' => 'SYN-001', 'group' => $syn],
                    ['code' => 'SYN-003', 'group' => $syn],
                    ['code' => 'GMN-001', 'group' => $syn],
                ],
            ],
            [
                'name'         => '石洗牛仔外套',
                'product_code' => 'TEX-003',
                'hs_code'      => '6201.92',
                'description'  => '100% 棉石洗牛仔外套，含拉鍊金屬配件，雙重合規（UFLPA + CMRT）',
                'suppliers' => [
                    ['code' => 'CTN-002', 'group' => $cotton],
                    ['code' => 'WVN-003', 'group' => $cotton],
                    ['code' => 'TRM-001', 'group' => $metal],
                    ['code' => 'GMN-002', 'group' => $cotton],
                ],
            ],
            [
                'name'         => '輕量羽絨背心',
                'product_code' => 'TEX-004',
                'hs_code'      => '6201.93',
                'description'  => '天然羽絨填充戶外背心，鈕扣木質飾片（EUDR），拉鍊（CMRT）',
                'suppliers' => [
                    ['code' => 'TRM-001', 'group' => $metal],
                    ['code' => 'TRM-002', 'group' => $natural],
                    ['code' => 'GMN-003', 'group' => $natural],
                ],
            ],
            [
                'name'         => '兒童環保 T 恤',
                'product_code' => 'TEX-005',
                'hs_code'      => '6109.10',
                'description'  => '有機棉認證兒童 T 恤，供歐盟市場，嚴格 UFLPA 溯源要求',
                'suppliers' => [
                    ['code' => 'CTN-003', 'group' => $cotton],
                    ['code' => 'GMN-005', 'group' => $cotton],
                ],
            ],
            [
                'name'         => '快乾機能衫',
                'product_code' => 'TEX-006',
                'hs_code'      => '6110.20',
                'description'  => '100% 再生聚酯快乾機能衫，採 rPET 纖維，SDS 化學安全管控',
                'suppliers' => [
                    ['code' => 'SYN-003', 'group' => $syn],
                    ['code' => 'WVN-001', 'group' => $syn],
                    ['code' => 'GMN-001', 'group' => $syn],
                ],
            ],
            [
                'name'         => '戶外機能夾克',
                'product_code' => 'TEX-007',
                'hs_code'      => '6201.30',
                'description'  => '防水透濕三層壓合夾克，含防水拉鍊（CMRT）及 DWR 防水助劑（SDS）',
                'suppliers' => [
                    ['code' => 'SYN-002', 'group' => $syn],
                    ['code' => 'WVN-001', 'group' => $syn],
                    ['code' => 'TRM-001', 'group' => $metal],
                    ['code' => 'CHM-001', 'group' => $dye],
                    ['code' => 'GMN-001', 'group' => $syn],
                ],
            ],
            [
                'name'         => '男性正裝棉麻襯衫',
                'product_code' => 'TEX-008',
                'hs_code'      => '6205.20',
                'description'  => '60% 棉 / 40% 亞麻混紡正裝襯衫，含金屬鈕扣，UFLPA + CMRT 雙合規',
                'suppliers' => [
                    ['code' => 'CTN-001', 'group' => $cotton],
                    ['code' => 'TRM-002', 'group' => $metal],
                    ['code' => 'DYE-001', 'group' => $dye],
                    ['code' => 'GMN-002', 'group' => $cotton],
                ],
            ],
        ];

        foreach ($products as $p) {
            $suppliersData = $p['suppliers'];
            unset($p['suppliers']);

            $product = SalesProduct::firstOrCreate(
                ['product_code' => $p['product_code']],
                array_merge($p, ['applicable_regulations' => []])
            );

            $product->syncInferredRegulations();
        }
    }
}
