<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 初始國家風險評等（source='manual'）
 * labor_risk: 勞工人權風險 1–5（ITUC 標準參考）
 * env_risk:   環境監管寬鬆程度 1–5（監管越寬鬆 → 分數越高）
 * geo_risk:   地緣政治穩定性風險 1–5
 */
class CountryRiskRatingSeeder extends Seeder
{
    public function run(): void
    {
        $ratings = [
            ['TW', '台灣',       1, 2, 1],
            ['VN', '越南',       3, 3, 2],
            ['CN', '中國大陸',   4, 4, 3],
            ['TH', '泰國',       3, 3, 2],
            ['IN', '印度',       4, 3, 2],
            ['ID', '印尼',       3, 3, 2],
            ['MY', '馬來西亞',   2, 2, 2],
            ['KR', '韓國',       2, 2, 1],
            ['BD', '孟加拉',     5, 4, 3],
            ['PK', '巴基斯坦',   4, 3, 4],
            ['MM', '緬甸',       5, 3, 5],
            ['ET', '衣索比亞',   4, 2, 4],
            ['LK', '斯里蘭卡',   3, 2, 3],
            ['JP', '日本',       1, 2, 1],
            ['US', '美國',       2, 2, 1],
            ['DE', '德國',       1, 2, 1],
            ['KH', '柬埔寨',     4, 3, 3],
            ['HK', '香港',       2, 2, 2],
        ];

        $now = now()->toDateTimeString();
        foreach ($ratings as [$code, $name, $labor, $env, $geo]) {
            DB::table('country_risk_ratings')->upsert([
                'id'           => (string) Str::uuid(),
                'country_code' => $code,
                'country_name' => $name,
                'labor_risk'   => $labor,
                'env_risk'     => $env,
                'geo_risk'     => $geo,
                'source'       => 'manual',
                'created_at'   => $now,
                'updated_at'   => $now,
            ], ['country_code'], ['country_name', 'labor_risk', 'env_risk', 'geo_risk', 'source', 'updated_at']);
        }

        $this->command->info('CountryRiskRatingSeeder: 完成，填入 ' . count($ratings) . ' 個國家評等');
    }
}
