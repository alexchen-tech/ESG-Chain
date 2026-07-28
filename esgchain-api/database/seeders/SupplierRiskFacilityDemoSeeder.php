<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DEMO 資料補齊：為尚無風險評估歷史或廠區資料的供應商各自建立最小可用的示範資料，
 * 不覆蓋/不重複建立既有真實資料（已有 risk_assessments 或 supplier_facilities 的
 * 供應商完全跳過）。
 */
class SupplierRiskFacilityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRiskHistory();
        $this->seedFacilities();
    }

    private function seedRiskHistory(): void
    {
        $suppliers = Supplier::whereDoesntHave('riskAssessments')->get();
        $now = now();

        foreach ($suppliers as $supplier) {
            $base = random_int(20, 70);

            foreach ([180, 90, 20] as $daysAgo) {
                $point = min(100, max(0, $base + random_int(-15, 15)));

                DB::table('risk_assessments')->insert([
                    'id' => (string) Str::orderedUuid(),
                    'supplier_id' => $supplier->id,
                    'e_probability' => 0, 'e_impact' => 0,
                    's_probability' => 0, 's_impact' => 0,
                    'g_probability' => 0, 'g_impact' => 0,
                    'gp_probability' => 0, 'gp_impact' => 0,
                    'assessed_at' => $now->copy()->subDays($daysAgo),
                    'notes' => 'Demo 資料 - 風險評估歷史',
                    'dim_e1' => min(100, max(0, $point + random_int(-10, 10))),
                    'dim_e2' => min(100, max(0, $point + random_int(-10, 10))),
                    'dim_e3' => min(100, max(0, $point + random_int(-10, 10))),
                    'dim_e4' => min(100, max(0, $point + random_int(-10, 10))),
                    'dim_e5' => min(100, max(0, $point + random_int(-10, 10))),
                    'dim_e6' => $point,
                    'assessment_version' => 'v3',
                    'source_type' => 'manual_review',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->command?->info("風險歷史：已為 {$suppliers->count()} 家供應商建立 demo 資料（各 3 筆歷史紀錄）");
    }

    private function seedFacilities(): void
    {
        $facilityTypes = [
            'manufacturing', 'manufacturing', 'manufacturing',
            'weaving', 'knitting', 'dyeing', 'printing', 'wet_processing', 'garment_assembly',
        ];
        $energyOptions = [
            ['electricity'],
            ['electricity', 'natural_gas'],
            ['electricity', 'diesel'],
            ['electricity', 'coal'],
            ['electricity', 'solar'],
        ];

        $suppliers = Supplier::whereDoesntHave('facilities')->get();
        $now = now();

        foreach ($suppliers as $supplier) {
            DB::table('supplier_facilities')->insert([
                'id' => (string) Str::orderedUuid(),
                'supplier_id' => $supplier->id,
                'name' => $supplier->name . ' 主要廠區',
                'country' => $supplier->country_code ?? 'TW',
                'address' => null,
                'facility_type' => $facilityTypes[array_rand($facilityTypes)],
                'energy_types' => json_encode($energyOptions[array_rand($energyOptions)]),
                'main_products' => null,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->command?->info("廠區資訊：已為 {$suppliers->count()} 家供應商建立 demo 主要廠區");
    }
}
