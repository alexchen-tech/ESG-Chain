<?php

namespace Database\Seeders;

use App\Models\MaterialGroup;
use Illuminate\Database\Seeder;

class MaterialGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            // ── 原物料群組（group_type = material）──
            [
                'name'               => '棉紡原料',
                'group_type'         => 'material',
                'description'        => '棉花、棉紗、棉布及相關棉紡織品。新疆棉花為 UFLPA 重點管控項目，須提供原產地申報及供應鏈溯源文件。',
                'hs_code_prefixes'   => ['5201', '5202', '5203', '5204', '5205', '5206', '5207', '5208', '5209', '5210', '5211', '5212', '52'],
                'required_doc_types' => ['UFLPA_DECLARATION', 'ORIGIN_CERT'],
                'is_system'          => true,
            ],
            [
                'name'               => '合成纖維原料',
                'group_type'         => 'material',
                'description'        => '聚酯纖維（PET）、尼龍（PA）、壓克力纖維、彈性纖維（Spandex），含 SVHC / REACH 管制化學物質風險。',
                'hs_code_prefixes'   => ['5402', '5403', '5404', '5405', '5406', '54', '5501', '5502', '5503', '5504', '5505', '5506', '5507'],
                'required_doc_types' => ['SDS'],
                'is_system'          => true,
            ],
            [
                'name'               => '天然纖維輔料',
                'group_type'         => 'material',
                'description'        => '天然橡膠底材、木質鈕扣、藤籐輔料等農業源頭物料，適用 EUDR 零毀林法規，需附盡職調查聲明與產地證明。',
                'hs_code_prefixes'   => ['4001', '4002', '4005', '4006', '4007', '40', '4401', '4402', '4407', '4412', '44'],
                'required_doc_types' => ['EUDR_DDS', 'ORIGIN_CERT'],
                'is_system'          => true,
            ],
            [
                'name'               => '染料化學品',
                'group_type'         => 'material',
                'description'        => '紡織染料（活性染料、分散染料）、印花漿、固色劑、後整理助劑，受 REACH / ZDHC MRSL 管制，須提供 SDS 安全資料表。',
                'hs_code_prefixes'   => ['3204', '3205', '3206', '3207', '3208', '3209', '3210', '32', '3814', '3402', '3809'],
                'required_doc_types' => ['SDS'],
                'is_system'          => true,
            ],
            [
                'name'               => '金屬配件',
                'group_type'         => 'material',
                'description'        => '拉鍊頭（YKK/SBS）、金屬鈕扣、鉚釘、扣環、D形環等金屬輔料，含衝突礦產（鉭、錫、鎢、金）風險，需 CMRT 申報。',
                'hs_code_prefixes'   => ['7616', '7419', '7320', '8308', '83', '96', '9606', '9607'],
                'required_doc_types' => ['CMRT'],
                'is_system'          => true,
            ],
            // ── 服務類群組（group_type = service）──
            [
                'name'               => '成衣縫製服務',
                'group_type'         => 'service',
                'description'        => '成衣裁縫、縫製加工服務廠，涉及棉質原料勞工追溯義務，需提供 UFLPA 勞工來源聲明。',
                'hs_code_prefixes'   => ['61', '62'],
                'required_doc_types' => ['UFLPA_DECLARATION'],
                'is_system'          => true,
            ],
            [
                'name'               => '染整加工服務',
                'group_type'         => 'service',
                'description'        => '織物染色、印花、後整理加工服務廠，使用化學品需符合 ZDHC MRSL，須提供製程化學品 SDS。',
                'hs_code_prefixes'   => ['3813', '3814', '3815'],
                'required_doc_types' => ['SDS'],
                'is_system'          => true,
            ],
            [
                'name'               => '木製包材服務',
                'group_type'         => 'service',
                'description'        => '使用木質包裝材料（紙箱、棧板、木箱）的服務供應商，適用 EUDR 零毀林法規，需 DDS 盡職調查聲明。',
                'hs_code_prefixes'   => ['4415', '4416', '4417'],
                'required_doc_types' => ['EUDR_DDS'],
                'is_system'          => true,
            ],
        ];

        foreach ($groups as $group) {
            MaterialGroup::firstOrCreate(
                ['name' => $group['name']],
                $group
            );
        }
    }
}
