<?php

namespace App\Observers;

use App\Jobs\RecomputeSupplierImpactJob;
use App\Models\Supplier;

class SupplierObserver
{
    /**
     * tier 或 spend_amount 變動（含 ERP sync）時重算 impact_score。
     *
     * 以非同步 Job 執行，避免批次 ERP sync 阻塞。ImpactScoreService 以 query builder
     * 寫回 impact_score，不會再次觸發本事件，故無遞迴風險。
     */
    public function updated(Supplier $supplier): void
    {
        if ($supplier->wasChanged('tier') || $supplier->wasChanged('spend_amount')) {
            RecomputeSupplierImpactJob::dispatch($supplier->id);
        }
    }

    /**
     * 新供應商建立後計算一次 impact_score（tier 有 default，spend 可能已帶入）。
     */
    public function created(Supplier $supplier): void
    {
        RecomputeSupplierImpactJob::dispatch($supplier->id);
    }
}
