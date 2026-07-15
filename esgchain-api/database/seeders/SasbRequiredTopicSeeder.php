<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SasbRequiredTopicSeeder extends Seeder
{
    public function run(): void
    {
        // 各 SASB 產業代碼的必調 ESG L3 topic（依 SASB 標準揭露主題對應）
        $topics = [
            // Iron & Steel Producers
            'EM-IS' => [
                'esg.env.ghg_emission',
                'esg.env.energy_consumption',
                'esg.env.water_usage',
                'esg.env.waste_management',
                'esg.ohs.incident_rate',
                'esg.labor.forced_labor',
            ],
            // Metals & Mining
            'EM-MM' => [
                'esg.env.ghg_emission',
                'esg.env.water_usage',
                'esg.env.biodiversity',
                'esg.env.waste_management',
                'esg.ohs.incident_rate',
                'esg.ohs.safety_mgmt',
            ],
            // Technology & Communications - Electronic Manufacturing Services
            'TC-ES' => [
                'esg.env.ghg_emission',
                'esg.env.energy_consumption',
                'esg.env.chemical_mgmt',
                'esg.labor.child_labor',
                'esg.labor.forced_labor',
                'esg.ohs.safety_mgmt',
                'esg.comm.data_privacy',
            ],
            // Transportation - Road Transportation
            'TR-MT' => [
                'esg.env.ghg_emission',
                'esg.env.energy_consumption',
                'esg.ohs.incident_rate',
                'esg.labor.working_hours',
                'esg.labor.wages',
            ],
            // Consumer Goods - Apparel, Accessories & Footwear
            'CG-AA' => [
                'esg.labor.child_labor',
                'esg.labor.forced_labor',
                'esg.labor.wages',
                'esg.labor.working_hours',
                'esg.ohs.safety_mgmt',
                'esg.env.chemical_mgmt',
            ],
            // Food & Beverage - Agricultural Products
            'FB-AG' => [
                'esg.env.water_usage',
                'esg.env.biodiversity',
                'esg.env.chemical_mgmt',
                'esg.labor.child_labor',
                'esg.labor.forced_labor',
                'esg.sc_env.raw_material',
            ],
        ];

        $now = now();

        foreach ($topics as $code => $slugs) {
            foreach ($slugs as $slug) {
                DB::table('sasb_required_topics')->updateOrInsert(
                    ['sasb_industry_code' => $code, 'tag_slug' => $slug],
                    [
                        'id'         => Str::uuid(),
                        'rationale'  => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }
}
