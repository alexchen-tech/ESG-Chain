<?php

namespace App\Observers;

use App\Jobs\CalculatePathRiskJob;
use App\Models\SupplierComplianceDoc;
use App\Models\TradeGoodPathRisk;
use App\Models\TradeGoodSupplier;

class SupplierComplianceDocPathRiskObserver
{
    // 文件狀態變動後，清除相關商品的路徑風險快取
    public function created(SupplierComplianceDoc $doc): void
    {
        $this->invalidateRelatedCache($doc);
    }

    public function updated(SupplierComplianceDoc $doc): void
    {
        $this->invalidateRelatedCache($doc);
    }

    private function invalidateRelatedCache(SupplierComplianceDoc $doc): void
    {
        // 找出此供應商參與的所有商品
        $tradeGoodIds = TradeGoodSupplier::where('supplier_id', $doc->supplier_id)
            ->pluck('trade_good_id');

        if ($tradeGoodIds->isEmpty()) {
            return;
        }

        TradeGoodPathRisk::whereIn('trade_good_id', $tradeGoodIds)->delete();
    }
}
