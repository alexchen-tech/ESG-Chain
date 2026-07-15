<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GeoEventDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('geo_events')->where('name', 'LIKE', 'DEMO%')->delete();

        $adminId = DB::table('users')->where('email', 'admin@esgchain.com')->value('id');
        $now = now();

        $events = [
            [
                'id'             => Str::uuid()->toString(),
                'name'           => '2025 美中關稅調升（Section 301）',
                'event_type'     => 'tariff_change',
                'affected_scope' => json_encode(['country_codes' => ['CN']]),
                'severity'       => 'high',
                'status'         => 'active',
                'occurred_at'    => '2025-04-15 00:00:00',
                'created_by_id'  => $adminId,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'id'             => Str::uuid()->toString(),
                'name'           => '越南勞工法修正（2025 Q2）',
                'event_type'     => 'country_risk_update',
                'affected_scope' => json_encode(['country_codes' => ['VN']]),
                'severity'       => 'medium',
                'status'         => 'active',
                'occurred_at'    => '2025-06-01 00:00:00',
                'created_by_id'  => $adminId,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
        ];

        DB::table('geo_events')->insert($events);

        // 為每個事件建立受影響供應商 review 記錄
        foreach ($events as $event) {
            $scope = json_decode($event['affected_scope'], true);
            $countryCodes = $scope['country_codes'] ?? [];

            $supplierIds = DB::table('suppliers')
                ->whereIn('country_code', $countryCodes)
                ->pluck('id');

            $latestRas = DB::table('risk_assessments')
                ->select('supplier_id', 'dim_e4')
                ->whereIn('supplier_id', $supplierIds)
                ->orderByDesc('assessed_at')
                ->get()
                ->keyBy('supplier_id');

            $reviews = [];
            foreach ($supplierIds as $supplierId) {
                $preE4 = $latestRas->get($supplierId)?->dim_e4;
                $postE4 = $preE4 !== null ? round($preE4 * (0.85 + lcg_value() * 0.1), 2) : null;

                $reviews[] = [
                    'id'           => Str::uuid()->toString(),
                    'geo_event_id' => $event['id'],
                    'supplier_id'  => $supplierId,
                    'status'       => 'done',
                    'pre_e4_score' => $preE4,
                    'post_e4_score' => $postE4,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }

            if (!empty($reviews)) {
                DB::table('geo_event_supplier_reviews')->insert($reviews);
            }
        }

        $this->command->info('GeoEvent DEMO data seeded: ' . count($events) . ' events.');
    }
}
