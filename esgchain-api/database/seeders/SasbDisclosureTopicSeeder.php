<?php

namespace Database\Seeders;

use App\Models\SasbDisclosureTopic;
use App\Models\SasbIndustry;
use Illuminate\Database\Seeder;

class SasbDisclosureTopicSeeder extends Seeder
{
    public function run(): void
    {
        // 依 SASB Industry code → Topics 定義
        $topicsByCode = [
            // Extractives & Minerals Processing
            'EM-IS' => [ // Iron & Steel Producers
                ['topic_name' => 'GHG Emissions', 'topic_code' => 'EM-IS-110a', 'esg_category' => 'E'],
                ['topic_name' => 'Air Quality', 'topic_code' => 'EM-IS-120a', 'esg_category' => 'E'],
                ['topic_name' => 'Energy Management', 'topic_code' => 'EM-IS-130a', 'esg_category' => 'E'],
                ['topic_name' => 'Water Management', 'topic_code' => 'EM-IS-140a', 'esg_category' => 'E'],
                ['topic_name' => 'Workforce Health & Safety', 'topic_code' => 'EM-IS-320a', 'esg_category' => 'S'],
                ['topic_name' => 'Labor Practices', 'topic_code' => 'EM-IS-310a', 'esg_category' => 'S'],
                ['topic_name' => 'Business Ethics & Transparency', 'topic_code' => 'EM-IS-510a', 'esg_category' => 'G'],
            ],
            'EM-MM' => [ // Metals & Mining
                ['topic_name' => 'GHG Emissions', 'topic_code' => 'EM-MM-110a', 'esg_category' => 'E'],
                ['topic_name' => 'Air Quality', 'topic_code' => 'EM-MM-120a', 'esg_category' => 'E'],
                ['topic_name' => 'Water Management', 'topic_code' => 'EM-MM-140a', 'esg_category' => 'E'],
                ['topic_name' => 'Biodiversity Impacts', 'topic_code' => 'EM-MM-160a', 'esg_category' => 'E'],
                ['topic_name' => 'Workforce Health & Safety', 'topic_code' => 'EM-MM-320a', 'esg_category' => 'S'],
                ['topic_name' => 'Community Relations', 'topic_code' => 'EM-MM-210a', 'esg_category' => 'S'],
                ['topic_name' => 'Business Ethics & Anti-corruption', 'topic_code' => 'EM-MM-510a', 'esg_category' => 'G'],
            ],
            // Resource Transformation
            'RC-CH' => [ // Chemicals
                ['topic_name' => 'GHG Emissions', 'topic_code' => 'RC-CH-110a', 'esg_category' => 'E'],
                ['topic_name' => 'Air Quality', 'topic_code' => 'RC-CH-120a', 'esg_category' => 'E'],
                ['topic_name' => 'Water Management', 'topic_code' => 'RC-CH-140a', 'esg_category' => 'E'],
                ['topic_name' => 'Hazardous Waste Management', 'topic_code' => 'RC-CH-150a', 'esg_category' => 'E'],
                ['topic_name' => 'Workforce Health & Safety', 'topic_code' => 'RC-CH-320a', 'esg_category' => 'S'],
                ['topic_name' => 'Product Design for Use-phase Efficiency', 'topic_code' => 'RC-CH-410a', 'esg_category' => 'G'],
            ],
            'RT-EE' => [ // Electrical & Electronic Equipment
                ['topic_name' => 'Energy Management', 'topic_code' => 'RT-EE-130a', 'esg_category' => 'E'],
                ['topic_name' => 'Hazardous Waste Management', 'topic_code' => 'RT-EE-150a', 'esg_category' => 'E'],
                ['topic_name' => 'Product Lifecycle Management', 'topic_code' => 'RT-EE-410a', 'esg_category' => 'E'],
                ['topic_name' => 'Labor Practices in Supply Chain', 'topic_code' => 'RT-EE-310a', 'esg_category' => 'S'],
                ['topic_name' => 'Workforce Diversity & Inclusion', 'topic_code' => 'RT-EE-330a', 'esg_category' => 'S'],
                ['topic_name' => 'Business Ethics', 'topic_code' => 'RT-EE-510a', 'esg_category' => 'G'],
            ],
            // Technology & Communications
            'TC-ES' => [ // Electronic Manufacturing Services & ODM
                ['topic_name' => 'Energy Management', 'topic_code' => 'TC-ES-130a', 'esg_category' => 'E'],
                ['topic_name' => 'Water Management', 'topic_code' => 'TC-ES-140a', 'esg_category' => 'E'],
                ['topic_name' => 'Hazardous Waste Management', 'topic_code' => 'TC-ES-150a', 'esg_category' => 'E'],
                ['topic_name' => 'Labor Practices', 'topic_code' => 'TC-ES-310a', 'esg_category' => 'S'],
                ['topic_name' => 'Employee Health & Safety', 'topic_code' => 'TC-ES-320a', 'esg_category' => 'S'],
                ['topic_name' => 'Conflict Minerals', 'topic_code' => 'TC-ES-440a', 'esg_category' => 'G'],
            ],
            'TC-SI' => [ // Software & IT Services
                ['topic_name' => 'Environmental Footprint of Hardware Infrastructure', 'topic_code' => 'TC-SI-130a', 'esg_category' => 'E'],
                ['topic_name' => 'Data Privacy & Freedom of Expression', 'topic_code' => 'TC-SI-220a', 'esg_category' => 'S'],
                ['topic_name' => 'Data Security', 'topic_code' => 'TC-SI-230a', 'esg_category' => 'S'],
                ['topic_name' => 'Recruiting & Managing Global Tech Workforce', 'topic_code' => 'TC-SI-330a', 'esg_category' => 'S'],
                ['topic_name' => 'Intellectual Property Protection', 'topic_code' => 'TC-SI-520a', 'esg_category' => 'G'],
            ],
            'TC-HW' => [ // Hardware
                ['topic_name' => 'Product Lifecycle Management', 'topic_code' => 'TC-HW-410a', 'esg_category' => 'E'],
                ['topic_name' => 'Energy Management', 'topic_code' => 'TC-HW-130a', 'esg_category' => 'E'],
                ['topic_name' => 'Supply Chain Management', 'topic_code' => 'TC-HW-430a', 'esg_category' => 'S'],
                ['topic_name' => 'Labor Practices in Supply Chain', 'topic_code' => 'TC-HW-310a', 'esg_category' => 'S'],
                ['topic_name' => 'Business Ethics', 'topic_code' => 'TC-HW-510a', 'esg_category' => 'G'],
            ],
            // Food & Beverage
            'FB-AG' => [ // Agricultural Products
                ['topic_name' => 'GHG Emissions & Water Usage', 'topic_code' => 'FB-AG-110a', 'esg_category' => 'E'],
                ['topic_name' => 'Land Use & Ecological Impacts', 'topic_code' => 'FB-AG-160a', 'esg_category' => 'E'],
                ['topic_name' => 'Supply Chain Management & Traceability', 'topic_code' => 'FB-AG-430a', 'esg_category' => 'S'],
                ['topic_name' => 'Labor Practices', 'topic_code' => 'FB-AG-310a', 'esg_category' => 'S'],
                ['topic_name' => 'Food Safety', 'topic_code' => 'FB-AG-250a', 'esg_category' => 'G'],
            ],
            // Transportation
            'TR-AP' => [ // Air Freight & Logistics
                ['topic_name' => 'GHG Emissions', 'topic_code' => 'TR-AP-110a', 'esg_category' => 'E'],
                ['topic_name' => 'Air Quality', 'topic_code' => 'TR-AP-120a', 'esg_category' => 'E'],
                ['topic_name' => 'Employee Health & Safety', 'topic_code' => 'TR-AP-320a', 'esg_category' => 'S'],
                ['topic_name' => 'Labor Practices', 'topic_code' => 'TR-AP-310a', 'esg_category' => 'S'],
                ['topic_name' => 'Business Ethics', 'topic_code' => 'TR-AP-510a', 'esg_category' => 'G'],
            ],
            // Financials
            'FN-AC' => [ // Asset Management & Custody Activities
                ['topic_name' => 'Transparent Information & Fair Advice', 'topic_code' => 'FN-AC-270a', 'esg_category' => 'S'],
                ['topic_name' => 'Employee Incentives & Risk Taking', 'topic_code' => 'FN-AC-510a', 'esg_category' => 'G'],
                ['topic_name' => 'Systemic Risk Management', 'topic_code' => 'FN-AC-550a', 'esg_category' => 'G'],
            ],
        ];

        foreach ($topicsByCode as $industryCode => $topics) {
            $industry = SasbIndustry::where('code', $industryCode)->first();
            if (!$industry) continue;

            foreach ($topics as $topic) {
                SasbDisclosureTopic::firstOrCreate(
                    ['sasb_industry_id' => $industry->id, 'topic_code' => $topic['topic_code']],
                    array_merge($topic, ['sasb_industry_id' => $industry->id])
                );
            }
        }
    }
}
