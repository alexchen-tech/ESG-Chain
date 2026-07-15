<?php

namespace App\Services\PCF;

use App\Models\SalesProduct;
use App\Models\ProductBomLine;
use App\Models\SupplierComplianceDoc;

class PcrCalculationService
{
    /**
     * 計算產品 PCR 比率
     *
     * 公式：R_PCR = Σ(W_i × P_i) / W_Total
     *
     * W_i = net_weight of BomLine's MaterialItem
     * P_i = pcr_percentage of BomLine's MaterialItem (as decimal)
     * W_Total = sum of all BomLine net_weights
     *
     * @return array{ pcr_ratio: float|null, incomplete_lines: int }
     */
    public function calcForProduct(SalesProduct $product): array
    {
        $bomLines = ProductBomLine::where('sales_product_id', $product->id)
            ->with(['materialItem'])
            ->get();

        $weightedSum  = 0.0;
        $totalWeight  = 0.0;
        $incompleteLines = 0;

        foreach ($bomLines as $line) {
            $item = $line->materialItem;
            if (!$item) {
                $incompleteLines++;
                continue;
            }

            $netWeight = $item->net_weight;
            if ($netWeight === null || $netWeight <= 0) {
                $incompleteLines++;
                continue;
            }

            $pcrPct = $item->pcr_percentage;
            if ($pcrPct === null) {
                $incompleteLines++;
                continue;
            }

            $totalWeight += $netWeight;
            $weightedSum += $netWeight * ($pcrPct / 100.0);
        }

        if ($totalWeight <= 0) {
            return ['pcr_ratio' => null, 'incomplete_lines' => $incompleteLines];
        }

        return [
            'pcr_ratio'        => round($weightedSum / $totalWeight, 4),
            'incomplete_lines' => $incompleteLines,
        ];
    }
}
