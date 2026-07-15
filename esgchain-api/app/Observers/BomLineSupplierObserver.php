<?php

namespace App\Observers;

use App\Jobs\PcfEmissionGapScanJob;
use App\Jobs\RecomputeSupplierImpactJob;
use App\Models\BomLineSupplier;

class BomLineSupplierObserver
{
    /**
     * 新建立 primary BomLineSupplier 時觸發缺口掃描
     */
    public function created(BomLineSupplier $bomLineSupplier): void
    {
        if ($bomLineSupplier->role === 'primary') {
            $this->dispatchScan($bomLineSupplier);
        }
        $this->recomputeImpact($bomLineSupplier->supplier_id);
    }

    /**
     * primary supplier 變更（role 更新為 primary）時觸發缺口掃描
     */
    public function updated(BomLineSupplier $bomLineSupplier): void
    {
        if ($bomLineSupplier->wasChanged('role') && $bomLineSupplier->role === 'primary') {
            $this->dispatchScan($bomLineSupplier);
        }
        $this->recomputeImpact($bomLineSupplier->supplier_id);
    }

    /**
     * BOM 供應關係移除也會改變單一來源依賴，需重算 impact。
     */
    public function deleted(BomLineSupplier $bomLineSupplier): void
    {
        $this->recomputeImpact($bomLineSupplier->supplier_id);
    }

    /**
     * 供應鏈供應關係變動 → 重算該供應商 Impact（單一來源/材料關鍵性因子）。
     */
    private function recomputeImpact(?string $supplierId): void
    {
        if ($supplierId) {
            RecomputeSupplierImpactJob::dispatch($supplierId);
        }
    }

    private function dispatchScan(BomLineSupplier $bomLineSupplier): void
    {
        $salesProductId = $bomLineSupplier->bomLine?->sales_product_id;

        PcfEmissionGapScanJob::dispatch(
            $salesProductId,
            $bomLineSupplier->supplier_id,
            'system_supplier_change',
        );
    }
}
