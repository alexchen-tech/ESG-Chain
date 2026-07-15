<?php

namespace Database\Seeders;

use App\Models\MaterialGroup;
use App\Models\MaterialItem;
use Illuminate\Database\Seeder;

class MaterialItemSeeder extends Seeder
{
    public function run(): void
    {
        $groups = MaterialGroup::pluck('id', 'name');

        $items = [
            // 棉紡原料
            ['item_code' => 'RAW-COT-001', 'name' => '精梳棉 32S', 'hs_code' => '52051100', 'unit' => 'KG', 'group' => '棉紡原料', 'desc' => '100% 精梳棉紗，32 支'],
            ['item_code' => 'RAW-COT-002', 'name' => '有機棉紗 40S', 'hs_code' => '52051200', 'unit' => 'KG', 'group' => '棉紡原料', 'desc' => 'GOTS 認證有機棉紗'],
            ['item_code' => 'RAW-COT-003', 'name' => '棉布坯布 60gsm', 'hs_code' => '52081100', 'unit' => 'YD', 'group' => '棉紡原料', 'desc' => '平織棉布，未漂染'],
            ['item_code' => 'RAW-COT-004', 'name' => '棉滌混紡紗 TC65/35', 'hs_code' => '52052100', 'unit' => 'KG', 'group' => '棉紡原料', 'desc' => '棉65% 滌35% 混紡'],

            // 合成纖維原料
            ['item_code' => 'RAW-SYN-001', 'name' => '聚酯纖維 DTY 150D', 'hs_code' => '54023300', 'unit' => 'KG', 'group' => '合成纖維原料', 'desc' => '彈力聚酯絲，150 丹尼爾'],
            ['item_code' => 'RAW-SYN-002', 'name' => '尼龍 6 紗 70D', 'hs_code' => '54024100', 'unit' => 'KG', 'group' => '合成纖維原料', 'desc' => '尼龍 6 長絲，70 丹尼爾'],
            ['item_code' => 'RAW-SYN-003', 'name' => '再生聚酯 rPET 75D', 'hs_code' => '54023300', 'unit' => 'KG', 'group' => '合成纖維原料', 'desc' => 'GRS 認證再生聚酯纖維'],
            ['item_code' => 'RAW-SYN-004', 'name' => '氨綸彈性纖維 40D', 'hs_code' => '54041100', 'unit' => 'KG', 'group' => '合成纖維原料', 'desc' => 'Spandex 氨綸絲'],
            ['item_code' => 'RAW-SYN-005', 'name' => '聚丙烯腈 (Acrylic) 紗', 'hs_code' => '55011000', 'unit' => 'KG', 'group' => '合成纖維原料', 'desc' => '腈綸短纖'],

            // 天然纖維輔料
            ['item_code' => 'RAW-NAT-001', 'name' => '羊毛紗 Merino 18.5μ', 'hs_code' => '51121100', 'unit' => 'KG', 'group' => '天然纖維輔料', 'desc' => 'Merino 美麗諾羊毛紗'],
            ['item_code' => 'RAW-NAT-002', 'name' => '麻纖維 (亞麻) 粗紗', 'hs_code' => '53101000', 'unit' => 'KG', 'group' => '天然纖維輔料', 'desc' => '亞麻粗紗，未漂白'],
            ['item_code' => 'RAW-NAT-003', 'name' => '真絲 (桑蠶) 22mm', 'hs_code' => '50072000', 'unit' => 'MTR', 'group' => '天然纖維輔料', 'desc' => '22 姆米桑蠶絲面料'],
            ['item_code' => 'RAW-NAT-004', 'name' => '天絲 Lyocell 面料', 'hs_code' => '55151100', 'unit' => 'YD', 'group' => '天然纖維輔料', 'desc' => 'Tencel 天絲®面料'],

            // 染料化學品
            ['item_code' => 'CHM-DYE-001', 'name' => '活性染料（紅）M-6B', 'hs_code' => '32041110', 'unit' => 'KG', 'group' => '染料化學品', 'desc' => 'OEKO-TEX 認證活性染料'],
            ['item_code' => 'CHM-DYE-002', 'name' => '分散染料（藍）E-R', 'hs_code' => '32041300', 'unit' => 'KG', 'group' => '染料化學品', 'desc' => '聚酯纖維用分散染料'],
            ['item_code' => 'CHM-DYE-003', 'name' => '固色劑（陽離子型）', 'hs_code' => '38099100', 'unit' => 'KG', 'group' => '染料化學品', 'desc' => '棉用固色整理劑'],
            ['item_code' => 'CHM-DYE-004', 'name' => '柔軟劑（矽酮型）', 'hs_code' => '38099100', 'unit' => 'KG', 'group' => '染料化學品', 'desc' => '矽酮柔軟整理劑，ZDHC Level 1'],
            ['item_code' => 'CHM-DYE-005', 'name' => '螢光增白劑 CBS-X', 'hs_code' => '32042000', 'unit' => 'KG', 'group' => '染料化學品', 'desc' => '棉纖維用螢光增白劑'],

            // 金屬配件
            ['item_code' => 'ACC-MET-001', 'name' => '黃銅拉鍊頭 5# YKK', 'hs_code' => '96072000', 'unit' => 'PCS', 'group' => '金屬配件', 'desc' => 'YKK 5號銅拉鍊頭'],
            ['item_code' => 'ACC-MET-002', 'name' => '不銹鋼四合扣 15mm', 'hs_code' => '96062200', 'unit' => 'SET', 'group' => '金屬配件', 'desc' => 'SUS304 四合扣，電鍍鎳'],
            ['item_code' => 'ACC-MET-003', 'name' => '鋁合金調節扣 25mm', 'hs_code' => '83089000', 'unit' => 'PCS', 'group' => '金屬配件', 'desc' => '背包用鋁合金日字扣'],
            ['item_code' => 'ACC-MET-004', 'name' => '銅製鉚釘 6mm', 'hs_code' => '83082000', 'unit' => 'PCS', 'group' => '金屬配件', 'desc' => '牛仔褲用銅鉚釘'],

            // 成衣縫製服務
            ['item_code' => 'SVC-CMT-001', 'name' => 'T-Shirt CMT 縫製服務', 'hs_code' => null, 'unit' => 'PCS', 'group' => '成衣縫製服務', 'desc' => 'T恤剪裁、縫製、整燙完整服務'],
            ['item_code' => 'SVC-CMT-002', 'name' => '牛仔褲 FOB 縫製', 'hs_code' => null, 'unit' => 'PCS', 'group' => '成衣縫製服務', 'desc' => '五袋牛仔褲完整製造含線材'],
            ['item_code' => 'SVC-CMT-003', 'name' => '功能性外套縫製', 'hs_code' => null, 'unit' => 'PCS', 'group' => '成衣縫製服務', 'desc' => '防風防水外套CMT服務'],

            // 染整加工服務
            ['item_code' => 'SVC-DYE-001', 'name' => '筒狀針織布染色服務', 'hs_code' => null, 'unit' => 'KG', 'group' => '染整加工服務', 'desc' => '成衣染色，散纖維或筒紗'],
            ['item_code' => 'SVC-DYE-002', 'name' => '防縮整理加工 (Sanforize)', 'hs_code' => null, 'unit' => 'YD', 'group' => '染整加工服務', 'desc' => '預縮防縮整理，含品測'],
            ['item_code' => 'SVC-DYE-003', 'name' => '防污塗層整理 (DWR)', 'hs_code' => null, 'unit' => 'YD', 'group' => '染整加工服務', 'desc' => 'PFC-free DWR 撥水整理'],

            // 木製包材服務
            ['item_code' => 'SVC-PKG-001', 'name' => '木棧板 EUR 標準型', 'hs_code' => '44151000', 'unit' => 'PCS', 'group' => '木製包材服務', 'desc' => 'EUR 規格 1200×800mm 木棧板，ISPM 15 熱處理'],
            ['item_code' => 'SVC-PKG-002', 'name' => '木箱包裝服務', 'hs_code' => '44151000', 'unit' => 'CBM', 'group' => '木製包材服務', 'desc' => '客製木箱包裝含熱處理認證'],
        ];

        foreach ($items as $data) {
            $groupId = isset($groups[$data['group']]) ? $groups[$data['group']] : null;

            MaterialItem::updateOrCreate(
                ['item_code' => $data['item_code']],
                [
                    'name'              => $data['name'],
                    'hs_code'           => $data['hs_code'],
                    'unit'              => $data['unit'],
                    'material_group_id' => $groupId,
                    'description'       => $data['desc'],
                    'is_active'         => true,
                ]
            );
        }
    }
}
