<?php

namespace Database\Seeders;

use App\Models\ProductionBatch;
use App\Models\RawMaterialOrigin;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class ProductionBatchSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = Supplier::take(3)->get();

        if ($suppliers->isEmpty()) return;

        $s1 = $suppliers[0]->id;
        $s2 = $suppliers[1]?->id ?? $s1;
        $s3 = $suppliers[2]?->id ?? $s1;

        $batches = [
            // 3 已匹配
            [
                'erp_batch_no'                => 'BATCH-2025-001',
                'erp_order_no'                => 'PO-20250301-A',
                'supplier_id'                 => $s1,
                'production_date'             => '2025-03-15',
                'quantity'                    => 5000.0000,
                'unit'                        => 'kg',
                'lot_pcf'                     => 2.45,
                'lot_pcf_source'              => 'reported',
                'source'                      => 'webhook',
                'erp_synced_at'               => now(),
                'origins' => [
                    [
                        'material_name'     => '有機棉',
                        'origin_country'    => 'IN',
                        'facility_name'     => 'Punjab Organic Farm',
                        'gps_lat'           => 30.900965,
                        'gps_lng'           => 75.857277,
                        'harvest_year'      => 2024,
                        'certification_ref' => 'GOTS-2024-IN-00432',
                    ],
                ],
            ],
            [
                'erp_batch_no'                => 'BATCH-2025-002',
                'erp_order_no'                => 'PO-20250302-B',
                'supplier_id'                 => $s2,
                'production_date'             => '2025-04-10',
                'quantity'                    => 3200.0000,
                'unit'                        => 'kg',
                'lot_pcf'                     => 3.10,
                'lot_pcf_source'              => 'estimated',
                'source'                      => 'csv',
                'erp_synced_at'               => now(),
                'origins' => [
                    [
                        'material_name'     => '再生聚酯纖維',
                        'origin_country'    => 'TW',
                        'facility_name'     => 'EcoFiber Taiwan Co.',
                        'gps_lat'           => 24.148218,
                        'gps_lng'           => 120.673648,
                        'harvest_year'      => null,
                        'certification_ref' => 'GRS-TW-2025-0087',
                    ],
                ],
            ],
            [
                'erp_batch_no'                => 'BATCH-2025-003',
                'erp_order_no'                => 'PO-20250401-C',
                'supplier_id'                 => $s3,
                'production_date'             => '2025-04-20',
                'quantity'                    => 8000.0000,
                'unit'                        => 'pcs',
                'lot_pcf'                     => null,
                'lot_pcf_source'              => null,
                'source'                      => 'manual',
                'erp_synced_at'               => null,
                'origins' => [
                    [
                        'material_name'     => '棉紗',
                        'origin_country'    => 'US',
                        'facility_name'     => 'Texas Cotton Cooperative',
                        'gps_lat'           => 33.425106,
                        'gps_lng'           => -101.855072,
                        'harvest_year'      => 2024,
                        'certification_ref' => 'BCI-USA-2024-TXC',
                    ],
                ],
            ],
            // 另 2 筆示範批號
            [
                'erp_batch_no'                => 'BATCH-2025-004',
                'erp_order_no'                => 'PO-20250501-D',
                'supplier_id'                 => $s1,
                'production_date'             => '2025-05-08',
                'quantity'                    => 1200.0000,
                'unit'                        => 'kg',
                'lot_pcf'                     => null,
                'lot_pcf_source'              => null,
                'source'                      => 'webhook',
                'erp_synced_at'               => now(),
                'origins' => [],
            ],
            [
                'erp_batch_no'                => 'BATCH-2025-005',
                'erp_order_no'                => null,
                'supplier_id'                 => $s2,
                'production_date'             => '2025-05-15',
                'quantity'                    => 600.0000,
                'unit'                        => 'kg',
                'lot_pcf'                     => null,
                'lot_pcf_source'              => null,
                'source'                      => 'csv',
                'erp_synced_at'               => now(),
                'origins' => [
                    [
                        'material_name'     => '大麻纖維',
                        'origin_country'    => 'CN',
                        'facility_name'     => 'Yunnan Hemp Mill',
                        'gps_lat'           => 25.045286,
                        'gps_lng'           => 102.710434,
                        'harvest_year'      => 2025,
                        'certification_ref' => null,
                    ],
                ],
            ],
        ];

        foreach ($batches as $batchData) {
            $origins = $batchData['origins'];
            unset($batchData['origins']);

            $batch = ProductionBatch::updateOrCreate(
                ['erp_batch_no' => $batchData['erp_batch_no']],
                $batchData
            );

            foreach ($origins as $originData) {
                RawMaterialOrigin::firstOrCreate(
                    ['production_batch_id' => $batch->id, 'material_name' => $originData['material_name']],
                    $originData
                );
            }
        }
    }
}
