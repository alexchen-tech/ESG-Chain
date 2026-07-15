<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierDisclosureDemoSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = DB::table('suppliers')->select('id', 'code', 'country_code')->get();
        $fields    = DB::table('supplier_disclosure_fields')->pluck('data_type', 'slug');
        $years     = [2023, 2024, 2025];

        // 每家供應商依 code 產生穩定的隨機種子
        $rows = [];
        foreach ($suppliers as $sup) {
            $seed = crc32($sup->code ?? $sup->id);

            // 數值基準（依供應商種子固定，每年略有變化）
            $scope1Base   = 300 + abs($seed % 2000);
            $scope2Base   = 500 + abs($seed % 3000);
            $energyBase   = 800000 + abs($seed % 5000000);
            $waterBase    = 20000 + abs($seed % 200000);
            $ltifr        = round((abs($seed % 50) / 10), 2);
            $femalePct    = 20 + abs($seed % 60);
            $recyclingPct = 30 + abs($seed % 55);
            $renewablePct = 5 + abs($seed % 45);

            // boolean 欄位：依 seed 決定，通常大多數有
            $hasAnti      = ($seed % 5) !== 0;
            $hasEsgReport = ($seed % 4) !== 0;
            $iso14001     = ($seed % 3) !== 0;
            $iso45001     = ($seed % 4) !== 1;
            $iso9001      = true;
            $noChildLabor = true;
            $auditDone    = ($seed % 3) !== 2;

            foreach ($years as $yr) {
                // 逐年改善趨勢（碳排下降、再生能源上升）
                $yFactor = $yr - 2023;  // 0, 1, 2
                $improveMult = 1 - ($yFactor * 0.04);  // 每年減 4%

                $supplierId = $sup->id;

                $numericFields = [
                    'ghg.scope1_mt_co2e'   => round($scope1Base * $improveMult, 1),
                    'ghg.scope2_mt_co2e'   => round($scope2Base * $improveMult, 1),
                    'energy.total_kwh'      => round($energyBase * $improveMult),
                    'water.total_m3'        => round($waterBase * (1 - $yFactor * 0.02)),
                    'safety.ltifr'          => round(max(0, $ltifr - $yFactor * 0.1), 2),
                    'diversity.female_pct'  => min(80, $femalePct + $yFactor * 2),
                    'waste.recycling_pct'   => min(95, $recyclingPct + $yFactor * 3),
                    'energy.renewable_pct'  => min(80, $renewablePct + $yFactor * 5),
                ];

                $booleanFields = [
                    'governance.has_anti_corruption_policy' => $hasAnti || $yFactor > 0,
                    'governance.has_esg_report'             => $hasEsgReport || $yFactor > 1,
                    'cert.iso14001'                         => $iso14001,
                    'cert.iso45001'                         => $iso45001 || $yFactor > 0,
                    'cert.iso9001'                          => $iso9001,
                    'labor.child_labor_banned'              => $noChildLabor,
                    'supply_chain.supplier_audit_conducted' => $auditDone || $yFactor > 0,
                ];

                foreach ($numericFields as $slug => $val) {
                    if (!isset($fields[$slug])) continue;
                    // 跳過已存在的記錄
                    $exists = DB::table('supplier_disclosures')
                        ->where('supplier_id', $supplierId)
                        ->where('field_slug', $slug)
                        ->where('period_year', $yr)
                        ->exists();
                    if ($exists) continue;

                    $rows[] = [
                        'id'            => Str::uuid()->toString(),
                        'supplier_id'   => $supplierId,
                        'field_slug'    => $slug,
                        'period_year'   => $yr,
                        'numeric_value' => $val,
                        'boolean_value' => null,
                        'text_value'    => null,
                        'source'        => 'manual',
                        'source_saq_id' => null,
                        'verified_at'   => $yr < 2025 ? now()->subMonths((2025 - $yr) * 12) : null,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];
                }

                foreach ($booleanFields as $slug => $val) {
                    if (!isset($fields[$slug])) continue;
                    $exists = DB::table('supplier_disclosures')
                        ->where('supplier_id', $supplierId)
                        ->where('field_slug', $slug)
                        ->where('period_year', $yr)
                        ->exists();
                    if ($exists) continue;

                    $rows[] = [
                        'id'            => Str::uuid()->toString(),
                        'supplier_id'   => $supplierId,
                        'field_slug'    => $slug,
                        'period_year'   => $yr,
                        'numeric_value' => null,
                        'boolean_value' => $val ? 1 : 0,
                        'text_value'    => null,
                        'source'        => 'manual',
                        'source_saq_id' => null,
                        'verified_at'   => $yr < 2025 ? now()->subMonths((2025 - $yr) * 12) : null,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];
                }

                // 批次寫入避免記憶體堆積
                if (count($rows) >= 200) {
                    DB::table('supplier_disclosures')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows) {
            DB::table('supplier_disclosures')->insert($rows);
        }
    }
}
