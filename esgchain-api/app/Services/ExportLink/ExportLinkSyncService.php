<?php

namespace App\Services\ExportLink;

use App\Models\BuyerProduct;
use App\Models\PcfSnapshot;

class ExportLinkSyncService
{
    public function syncFromPcf(BuyerProduct $product, PcfSnapshot $snapshot): void
    {
        if ($snapshot->total_pcf === null) {
            return;
        }

        $product->exportLinks()
            ->where('relation_type', 'finished_good')
            ->with('tradeGood')
            ->get()
            ->each(function ($link) use ($snapshot) {
                $link->tradeGood->update([
                    'embedded_emissions'   => $snapshot->total_pcf,
                    'emissions_source'     => 'pcf_sync',
                    'emissions_updated_at' => now(),
                ]);
            });
    }

    public function syncEudrFromRegulations(BuyerProduct $product): void
    {
        $regulations = $product->inferred_regulations ?? [];

        if (!in_array('EUDR', $regulations, true)) {
            return;
        }

        $product->exportLinks()
            ->where('relation_type', 'finished_good')
            ->with('tradeGood')
            ->get()
            ->each(function ($link) {
                $link->tradeGood->update(['is_eudr_applicable' => true]);
            });
    }
}
