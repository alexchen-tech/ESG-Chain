<?php

namespace Database\Seeders;

use App\Models\SupplierGroup;
use Illuminate\Database\Seeder;

class SupplierGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['name' => '棉紡紗線供應商',   'description' => '棉花原料、棉紗、棉紡廠，含 UFLPA 合規管控重點'],
            ['name' => '合成纖維供應商',   'description' => '聚酯纖維、尼龍、彈性纖維（Spandex）等化纖原料廠'],
            ['name' => '織布染整廠',       'description' => '梭織布、針織布製造，及染色、後整理加工廠'],
            ['name' => '成衣製造商',       'description' => 'CMT / OEM 成衣廠，Tier 1 直接製造夥伴'],
            ['name' => '輔料配件商',       'description' => '拉鍊、鈕扣、彈性帶、織標、吊牌等服裝輔料'],
            ['name' => '染料化學品商',     'description' => '紡織染料、印花漿、助劑、後整理化學品（ZDHC/REACH 重點管控）'],
            ['name' => '包材物流商',       'description' => '成品外箱包材、吊牌印刷，及國際貨運物流服務商'],
        ];

        foreach ($groups as $g) {
            SupplierGroup::firstOrCreate(['name' => $g['name']], ['description' => $g['description']]);
        }
    }
}
