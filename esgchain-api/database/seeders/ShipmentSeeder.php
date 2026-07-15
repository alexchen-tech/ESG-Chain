<?php

namespace Database\Seeders;

use App\Models\ProductionBatch;
use App\Models\Shipment;
use App\Models\ShipmentLine;
use App\Models\ShipmentLineBatch;
use App\Models\TradeGood;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShipmentSeeder extends Seeder
{
    public function run(): void
    {
        $user        = User::first();
        $eudrTg      = TradeGood::where('is_eudr_applicable', true)->first();
        $normalTg    = TradeGood::where('is_eudr_applicable', false)->first();
        $batches     = ProductionBatch::take(2)->get();

        // Shipment 1: EU market, EUDR draft, with allocated batches
        $s1 = Shipment::create([
            'shipment_no'     => 'SHIP-202606-001',
            'target_market'   => 'EU',
            'export_date'     => '2026-07-15',
            'eudr_dds_status' => 'draft',
            'created_by'      => $user?->id,
            'notes'           => 'EUDR 示範申報批次，含已分配生產批號',
        ]);

        if ($eudrTg && $s1) {
            $line1 = ShipmentLine::create([
                'shipment_id'      => $s1->id,
                'sales_product_id' => $eudrTg->id,
                'total_quantity'   => 500.00,
                'unit'             => 'kg',
                'weighted_pcf'     => null,
            ]);

            if ($batches->isNotEmpty()) {
                ShipmentLineBatch::updateOrCreate(
                    ['shipment_line_id' => $line1->id, 'production_batch_id' => $batches[0]->id],
                    ['allocated_quantity' => 300.00]
                );
                if ($batches->count() >= 2) {
                    ShipmentLineBatch::updateOrCreate(
                        ['shipment_line_id' => $line1->id, 'production_batch_id' => $batches[1]->id],
                        ['allocated_quantity' => 200.00]
                    );
                }
                // Recalc weighted PCF
                $allBatches = $line1->lineBatches()->with('productionBatch')->get();
                $totalQty   = $allBatches->sum('allocated_quantity');
                $hasMissing = $allBatches->contains(fn($lb) => is_null($lb->productionBatch?->lot_pcf));
                if (!$hasMissing && $totalQty > 0) {
                    $wpf = $allBatches->sum(fn($lb) => $lb->allocated_quantity * $lb->productionBatch->lot_pcf) / $totalQty;
                    $line1->update(['weighted_pcf' => round($wpf, 6)]);
                }
            }
        }

        // Shipment 2: JP market, not EUDR required, no lines
        Shipment::create([
            'shipment_no'     => 'SHIP-202606-002',
            'target_market'   => 'JP',
            'export_date'     => '2026-07-20',
            'eudr_dds_status' => 'not_required',
            'created_by'      => $user?->id,
            'notes'           => '日本市場出口，不適用 EUDR DDS',
        ]);
    }
}
