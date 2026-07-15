<?php

namespace App\Services\Shipment;

use App\Models\Customer;
use App\Models\ProductionBatch;
use App\Models\SalesProduct;
use App\Models\Shipment;
use App\Models\ShipmentLine;
use App\Models\ShipmentLineBatch;
use App\Models\TradeGood;
use App\Models\User;
use App\Services\Compliance\MarketComplianceChecker;

class ShipmentService
{
    /**
     * Create a new Shipment. Auto-generates shipment_no if not provided.
     */
    public function create(array $data, User $user): array
    {
        if (empty($data['shipment_no'])) {
            $data['shipment_no'] = $this->generateShipmentNo();
        }

        $data['created_by']      = $user->id;
        $data['eudr_dds_status'] = $data['eudr_dds_status'] ?? 'draft';

        $warnings = [];

        // 從客戶國家自動帶入 target_market
        if (!empty($data['customer_id'])) {
            $customer = Customer::find($data['customer_id']);
            if ($customer) {
                if (empty($data['target_market'])) {
                    $data['target_market'] = $this->deriveTargetMarket($customer->country_code);
                }
                if ($customer->customer_type === 'agent') {
                    $warnings[] = '代理商可能非實際 CBAM 進口商，請確認實際進口商身份。';
                }
            }
        }

        $shipment = Shipment::create($data);

        return ['shipment' => $shipment, 'warnings' => $warnings];
    }

    private function deriveTargetMarket(string $countryCode): string
    {
        $eu = ['AT','BE','BG','CY','CZ','DE','DK','EE','ES','FI',
               'FR','GR','HR','HU','IE','IT','LT','LU','LV','MT',
               'NL','PL','PT','RO','SE','SI','SK'];
        $apac = ['CN','JP','KR','TW','SG','HK','AU','NZ','VN','TH','MY','ID','PH','IN'];
        $na   = ['US','CA','MX'];

        if (in_array($countryCode, $eu, true))   return 'EU';
        if (in_array($countryCode, $na, true))   return 'NA';
        if (in_array($countryCode, $apac, true)) return 'APAC';
        return $countryCode;
    }

    /**
     * Add a ShipmentLine (trade good item) to a Shipment.
     * Checks market-based EUDR compliance to decide whether eudr_dds_status should be set to 'draft'.
     */
    public function addLine(Shipment $shipment, array $data): ShipmentLine
    {
        // 6.5: 鎖定此時點的 PCF 快照
        if (!empty($data['sales_product_id']) && empty($data['pcf_snapshot_id'])) {
            $product = SalesProduct::find($data['sales_product_id']);
            $latestSnapshot = $product?->latestPcfSnapshot();
            if ($latestSnapshot) {
                $data['pcf_snapshot_id'] = $latestSnapshot->id;
            }
        }

        $line = $shipment->lines()->create($data);
        $line->load('salesProduct');

        if ($line->salesProduct && $shipment->target_market && $shipment->eudr_dds_status === 'not_required') {
            $checker = app(MarketComplianceChecker::class);
            // MarketComplianceChecker::check() 型別提示為 TradeGood；TradeGood 是 SalesProduct 的別名子類別
            // （同一張 sales_products 表），用 TradeGood::find() 重新取得以滿足型別，不更動 checker 本身
            $tradeGoodForCheck = TradeGood::find($line->sales_product_id);
            $result  = $checker->check($tradeGoodForCheck, $shipment->target_market);

            $eudrMissing = collect($result['results'])->contains(
                fn ($r) => $r['doc_type'] === 'EUDR_DDS' && in_array($r['status'], ['missing', 'expired'])
            );

            if ($eudrMissing) {
                $shipment->update(['eudr_dds_status' => 'draft']);
            }
        }

        return $line;
    }

    /**
     * Allocate a ProductionBatch quantity to a ShipmentLine.
     * Returns ['lineBatch' => ShipmentLineBatch, 'warnings' => string[]].
     */
    public function allocateBatch(ShipmentLine $line, ProductionBatch $batch, float $qty): array
    {
        $lineBatch = ShipmentLineBatch::updateOrCreate(
            ['shipment_line_id' => $line->id, 'production_batch_id' => $batch->id],
            ['allocated_quantity' => $qty]
        );

        $warnings = [];
        $totalAllocated = ShipmentLineBatch::where('production_batch_id', $batch->id)
            ->sum('allocated_quantity');

        if ($totalAllocated > $batch->quantity) {
            $over = $totalAllocated - $batch->quantity;
            $warnings[] = "批號 {$batch->erp_batch_no} 已超額分配 {$over} {$batch->unit}";
        }

        $this->recalcWeightedPcf($line);

        return ['lineBatch' => $lineBatch, 'warnings' => $warnings];
    }

    /**
     * Recalculate weighted average PCF for a ShipmentLine from its allocated batches.
     */
    public function recalcWeightedPcf(ShipmentLine $line): void
    {
        $line->load('lineBatches.productionBatch');
        $batches = $line->lineBatches;

        if ($batches->isEmpty()) {
            $line->update(['weighted_pcf' => null]);
            return;
        }

        $totalQty   = 0;
        $weightedSum = 0;
        $hasNull    = false;

        foreach ($batches as $lb) {
            $batch = $lb->productionBatch;
            if ($batch->lot_pcf === null) {
                $hasNull = true;
                break;
            }
            $totalQty    += $lb->allocated_quantity;
            $weightedSum += $batch->lot_pcf * $lb->allocated_quantity;
        }

        $line->update([
            'weighted_pcf' => ($hasNull || $totalQty == 0) ? null : round($weightedSum / $totalQty, 4),
        ]);
    }

    /**
     * Generate a structured DDS draft JSON for a Shipment.
     */
    public function generateDdsDraft(Shipment $shipment): array
    {
        $shipment->load([
            'lines.salesProduct',
            'lines.lineBatches.productionBatch.supplier',
            'lines.lineBatches.productionBatch.rawMaterialOrigins',
            'creator',
        ]);

        $commodities = $shipment->lines->map(function (ShipmentLine $line) {
            $tg = $line->salesProduct;
            $batches = $line->lineBatches->map(function (ShipmentLineBatch $lb) {
                $batch   = $lb->productionBatch;
                $origins = $batch->rawMaterialOrigins->map(fn($o) => [
                    'material'      => $o->material_name,
                    'country'       => $o->origin_country,
                    'facility'      => $o->facility_name,
                    'gps'           => ($o->gps_lat && $o->gps_lng) ? "{$o->gps_lat}, {$o->gps_lng}" : null,
                    'harvest_year'  => $o->harvest_year,
                    'certification' => $o->certification_ref,
                ])->values()->toArray();

                return [
                    'batch_no'              => $batch->erp_batch_no,
                    'supplier'              => $batch->supplier?->name,
                    'allocated_quantity'    => (float) $lb->allocated_quantity,
                    'lot_pcf'               => $batch->lot_pcf ? (float) $batch->lot_pcf : null,
                    'raw_material_origins'  => $origins,
                    'origins_missing'       => empty($origins),
                ];
            })->values()->toArray();

            return [
                'trade_good_code'  => $tg?->product_code,
                'trade_good_name'  => $tg?->name,
                'hs_code'          => $line->hs_code_override ?? $tg?->hs_code,
                'total_quantity'   => (float) $line->total_quantity,
                'unit'             => $line->unit,
                'weighted_pcf'     => $line->weighted_pcf ? (float) $line->weighted_pcf : null,
                'production_batches' => $batches,
            ];
        })->values()->toArray();

        return [
            'shipment_no'       => $shipment->shipment_no,
            'target_market'     => $shipment->target_market,
            'export_date'       => $shipment->export_date?->toDateString(),
            'eudr_dds_status'   => $shipment->eudr_dds_status,
            'commodities'       => $commodities,
            'eudr_risk_assessment' => 'pending',
            'generated_at'      => now()->toISOString(),
        ];
    }

    private function generateShipmentNo(): string
    {
        $prefix = 'SHIP-' . now()->format('Ym') . '-';
        $last   = Shipment::where('shipment_no', 'like', $prefix . '%')
            ->orderByDesc('shipment_no')->value('shipment_no');
        $seq    = $last ? ((int) substr($last, -3)) + 1 : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
