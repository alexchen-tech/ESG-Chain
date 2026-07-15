<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FrameworkDefaultWeightSeeder extends Seeder
{
    public function run(): void
    {
        $frameworks = [
            'ESG' => [
                ['pillar_slug' => 'esg.env', 'weight' => 0.40, 'sort_order' => 0],
                ['pillar_slug' => 'esg.soc', 'weight' => 0.35, 'sort_order' => 1],
                ['pillar_slug' => 'esg.gov', 'weight' => 0.25, 'sort_order' => 2],
            ],
            'ISO20400' => [
                ['pillar_slug' => 'iso20400.policy',      'weight' => 0.20, 'sort_order' => 0],
                ['pillar_slug' => 'iso20400.due_diligence','weight' => 0.20, 'sort_order' => 1],
                ['pillar_slug' => 'iso20400.action',       'weight' => 0.20, 'sort_order' => 2],
                ['pillar_slug' => 'iso20400.reporting',    'weight' => 0.15, 'sort_order' => 3],
                ['pillar_slug' => 'iso20400.capacity',     'weight' => 0.15, 'sort_order' => 4],
                ['pillar_slug' => 'iso20400.stakeholder',  'weight' => 0.10, 'sort_order' => 5],
            ],
            'ISO26000' => [
                ['pillar_slug' => 'iso26000.governance',  'weight' => 0.20, 'sort_order' => 0],
                ['pillar_slug' => 'iso26000.hr',          'weight' => 0.15, 'sort_order' => 1],
                ['pillar_slug' => 'iso26000.labor',       'weight' => 0.15, 'sort_order' => 2],
                ['pillar_slug' => 'iso26000.environment', 'weight' => 0.20, 'sort_order' => 3],
                ['pillar_slug' => 'iso26000.fairop',      'weight' => 0.15, 'sort_order' => 4],
                ['pillar_slug' => 'iso26000.consumer',    'weight' => 0.10, 'sort_order' => 5],
                ['pillar_slug' => 'iso26000.community',   'weight' => 0.05, 'sort_order' => 6],
            ],
            'Geo-Risk' => [
                ['pillar_slug' => 'georisk.political',    'weight' => 0.30, 'sort_order' => 0],
                ['pillar_slug' => 'georisk.environmental','weight' => 0.25, 'sort_order' => 1],
                ['pillar_slug' => 'georisk.social',       'weight' => 0.25, 'sort_order' => 2],
                ['pillar_slug' => 'georisk.regulatory',   'weight' => 0.20, 'sort_order' => 3],
            ],
        ];

        $now = now();

        foreach ($frameworks as $framework => $pillars) {
            foreach ($pillars as $pillar) {
                DB::table('framework_default_weights')->updateOrInsert(
                    ['scoring_framework' => $framework, 'pillar_slug' => $pillar['pillar_slug']],
                    [
                        'id'         => Str::uuid(),
                        'weight'     => $pillar['weight'],
                        'sort_order' => $pillar['sort_order'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }
}
