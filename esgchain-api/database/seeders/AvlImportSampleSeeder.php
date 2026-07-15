<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * 產生一份 AVL 範例 CSV 檔案供測試匯入使用
 * 執行後可於 storage/app/samples/avl_sample.csv 找到檔案
 */
class AvlImportSampleSeeder extends Seeder
{
    public function run(): void
    {
        $dir = storage_path('app/samples');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $csv = implode("\n", [
            'supplier_code,supplier_name,country_code,tier,material_group_code,approved_items',
            'SUP-DEMO-001,台灣示範鋼鐵股份有限公司,TW,1,鋼鐵原料,"RAW-COT-001,RAW-COT-002"',
            'SUP-DEMO-002,越南綠源環保科技有限公司,VN,2,化學品,RAW-COT-003',
            'SUP-DEMO-003,德國工業材料 GmbH,DE,1,電子零件,',
        ]);

        file_put_contents($dir . '/avl_sample.csv', $csv);

        $this->command->info('AVL 範例 CSV 已產生：storage/app/samples/avl_sample.csv');
    }
}
