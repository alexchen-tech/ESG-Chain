<?php

namespace Database\Seeders;

use App\Models\MarketDefinition;
use Illuminate\Database\Seeder;

class MarketDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $markets = [
            ['code' => 'US_MARKET',  'label' => '美國市場',   'description' => '適用 UFLPA 強制勞動預防法，棉花、聚矽等原料需提供溯源申報'],
            ['code' => 'EU_MARKET',  'label' => '歐盟市場',   'description' => '適用 EUDR 零毀林法規、REACH 化學品管制、CE 標誌要求'],
            ['code' => 'UK_MARKET',  'label' => '英國市場',   'description' => '脫歐後適用 UKCA 認證，化學品管制參照 UK REACH'],
            ['code' => 'JP_MARKET',  'label' => '日本市場',   'description' => '適用 JIS 標準及日本消費品安全法（PSC）'],
            ['code' => 'GLOBAL',     'label' => '全球通用',   'description' => '無特定市場法規，適用通用基準要求'],
        ];

        foreach ($markets as $market) {
            MarketDefinition::firstOrCreate(
                ['code' => $market['code']],
                array_merge($market, ['is_system' => true])
            );
        }
    }
}
