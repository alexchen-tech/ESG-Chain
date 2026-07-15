<?php

namespace App\Observers;

use App\Models\MaterialGroup;
use App\Models\SupplierGroup;
use App\Models\TradeGood;

class TradeGoodObserver
{
    public function saving(TradeGood $tradeGood): void
    {
        if ($tradeGood->isDirty('hs_code') && !$tradeGood->isDirty('material_group_id')) {
            $group = MaterialGroup::findByHsCode($tradeGood->hs_code);
            $tradeGood->material_group_id = $group?->id;
        }
    }

    public function saved(TradeGood $tradeGood): void
    {
        $this->clearGroupCache($tradeGood);
    }

    public function deleted(TradeGood $tradeGood): void
    {
        $this->clearGroupCache($tradeGood);
    }

    private function clearGroupCache(TradeGood $tradeGood): void
    {
        $supplierIds = $tradeGood->tradeGoodSuppliers()->pluck('supplier_id');

        if ($supplierIds->isEmpty()) {
            return;
        }

        SupplierGroup::whereHas('suppliers', fn($q) =>
            $q->whereIn('id', $supplierIds)
        )->each(fn($sg) => $sg->clearInferredCache());
    }
}
