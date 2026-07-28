<?php

namespace App\Observers;

use App\Jobs\CalculatePathRiskJob;
use App\Models\BomLineSupplier;
use App\Models\MaterialItemSupplier;
use App\Models\ProductBomLine;
use App\Models\SupplierComplianceDoc;
use App\Models\TradeGoodPathRisk;

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
        // 找出此供應商參與的所有商品：BOM 行已直接登記者（bom_line_suppliers），
        // 或該行物料的核可清單含此供應商者（material_item_suppliers）
        $bomLineIds = BomLineSupplier::where('supplier_id', $doc->supplier_id)->pluck('bom_line_id');
        $materialItemIds = MaterialItemSupplier::where('supplier_id', $doc->supplier_id)->pluck('material_item_id');

        $tradeGoodIds = ProductBomLine::where(function ($q) use ($bomLineIds, $materialItemIds) {
            $q->whereIn('id', $bomLineIds)
              ->orWhereIn('material_item_id', $materialItemIds);
        })->pluck('sales_product_id')->unique();

        if ($tradeGoodIds->isEmpty()) {
            return;
        }

        TradeGoodPathRisk::whereIn('trade_good_id', $tradeGoodIds)->delete();
    }
}
