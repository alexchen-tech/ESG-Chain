<?php

namespace App\Services\Chemical;

use App\Models\ChemicalComplianceAlert;
use App\Models\SalesProduct;

/**
 * 即時彙總產品的有害物質揭露狀態，不落地為靜態欄位——判定結果隨
 * chemical_compliance_alerts 的稽核狀態變動，落地會產生跟既有稽核紀錄
 * 不同調的二次真相。
 */
class HazardDisclosureService
{
    public function checkProduct(SalesProduct $product): array
    {
        $alerts = ChemicalComplianceAlert::where('sales_product_id', $product->id)
            ->where('status', '!=', 'resolved')
            ->with('chemical:id,substance_name')
            ->get();

        return [
            'has_hazardous_substance' => $alerts->isNotEmpty(),
            'alerts' => $alerts->map(fn ($a) => [
                'chemical_id'   => $a->chemical_id,
                'chemical_name' => $a->chemical?->substance_name,
                'alert_level'   => $a->alert_level,
                'status'        => $a->status,
            ])->values()->toArray(),
        ];
    }
}
