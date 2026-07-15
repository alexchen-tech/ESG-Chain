<?php

namespace App\Observers;

use App\Models\PcfSnapshot;
use App\Models\TradeGoodPathRisk;

class PcfSnapshotPathRiskObserver
{
    // snapshot 建立後，清除對應商品的路徑風險快取，觸發重算
    public function created(PcfSnapshot $snapshot): void
    {
        $this->invalidateCache($snapshot);
    }

    private function invalidateCache(PcfSnapshot $snapshot): void
    {
        TradeGoodPathRisk::where('trade_good_id', $snapshot->product_id)->delete();
    }
}
