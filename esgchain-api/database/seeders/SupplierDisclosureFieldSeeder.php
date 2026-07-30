<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierDisclosureFieldSeeder extends Seeder
{
    public function run(): void
    {
        $fields = [
            ['slug' => 'ghg.scope1_mt_co2e',                    'label' => 'Scope 1 溫室氣體排放量',        'data_type' => 'numeric',        'unit' => 'mt_co2e',           'period_type' => 'annual'],
            ['slug' => 'ghg.scope2_mt_co2e',                    'label' => 'Scope 2 溫室氣體排放量',        'data_type' => 'numeric',        'unit' => 'mt_co2e',           'period_type' => 'annual'],
            ['slug' => 'ghg.scope3_mt_co2e',                    'label' => '範疇三排放量（噸CO2e）',        'data_type' => 'numeric',        'unit' => 'mt_co2e',           'period_type' => 'annual'],
            ['slug' => 'energy.total_kwh',                       'label' => '總用電量',                     'data_type' => 'numeric',        'unit' => 'kWh',               'period_type' => 'annual'],
            ['slug' => 'water.total_m3',                         'label' => '總用水量',                     'data_type' => 'numeric',        'unit' => 'm³',                'period_type' => 'annual'],
            ['slug' => 'safety.ltifr',                           'label' => '職業災害失能傷害頻率（LTIFR）', 'data_type' => 'numeric',        'unit' => 'per_million_hrs',   'period_type' => 'annual'],
            ['slug' => 'diversity.female_pct',                   'label' => '女性員工比例',                  'data_type' => 'numeric',        'unit' => '%',                 'period_type' => 'annual'],
            ['slug' => 'governance.has_anti_corruption_policy',  'label' => '具備反腐敗政策',                'data_type' => 'boolean',        'unit' => null,                'period_type' => 'annual'],
            ['slug' => 'governance.has_esg_report',              'label' => '出具 ESG 報告',                'data_type' => 'boolean',        'unit' => null,                'period_type' => 'annual'],
            ['slug' => 'cert.iso14001',                          'label' => '持有 ISO 14001 認證',          'data_type' => 'boolean',        'unit' => null,                'period_type' => 'point_in_time'],
            ['slug' => 'cert.iso45001',                          'label' => '持有 ISO 45001 認證',          'data_type' => 'boolean',        'unit' => null,                'period_type' => 'point_in_time'],
            ['slug' => 'cert.iso9001',                           'label' => '持有 ISO 9001 認證',           'data_type' => 'boolean',        'unit' => null,                'period_type' => 'point_in_time'],
            ['slug' => 'waste.recycling_pct',                    'label' => '廢棄物回收率',                  'data_type' => 'numeric',        'unit' => '%',                 'period_type' => 'annual'],
            ['slug' => 'labor.child_labor_banned',               'label' => '明令禁止童工與強迫勞動',        'data_type' => 'boolean',        'unit' => null,                'period_type' => 'annual'],
            ['slug' => 'supply_chain.supplier_audit_conducted',  'label' => '執行供應商盡職調查',            'data_type' => 'boolean',        'unit' => null,                'period_type' => 'annual'],
            ['slug' => 'energy.renewable_pct',                   'label' => '再生能源使用比例',              'data_type' => 'numeric',        'unit' => '%',                 'period_type' => 'annual'],
        ];

        foreach ($fields as $field) {
            DB::table('supplier_disclosure_fields')->updateOrInsert(
                ['slug' => $field['slug']],
                array_merge($field, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
