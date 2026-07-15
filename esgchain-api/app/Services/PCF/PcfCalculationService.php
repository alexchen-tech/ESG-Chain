<?php

namespace App\Services\PCF;

use App\Models\PcfSnapshot;
use App\Models\ProductBomLine;
use App\Models\SalesProduct;
use Illuminate\Support\Facades\Log;

class PcfCalculationService
{
    private PcrCalculationService $pcrService;

    public function __construct(
        private readonly MaterialEmissionService $emissionService,
    ) {
        $this->pcrService = app(PcrCalculationService::class);
    }

    /**
     * 遍歷 BomLine，取 primary supplier 最佳碳排，計算各行小計，加總 total_pcf，判斷 iso14067_ready。
     * 型態 B（child_sales_product_id）從子產品最新 PCF 快照取 total_pcf。
     */
    public function calcForProduct(SalesProduct $product): array
    {
        $lines         = $this->buildSnapshotLines($product);
        $totalPcf      = 0.0;
        $iso14067Ready = true;

        foreach ($lines as $line) {
            if ($line['emission_per_unit'] !== null) {
                $totalPcf += $line['subtotal'] ?? 0.0;
            } else {
                $iso14067Ready = false;
            }

            if ($line['is_estimated'] || $line['emission_source'] === 'ai-estimated') {
                $iso14067Ready = false;
            }
        }

        return [
            'total_pcf'      => $totalPcf > 0 ? $totalPcf : null,
            'iso14067_ready' => $iso14067Ready && count($lines) > 0,
            'lines'          => $lines,
        ];
    }

    /**
     * 產生 lines JSON 陣列，支援型態 A（material_item_id）與型態 B（child_sales_product_id）
     */
    public function buildSnapshotLines(SalesProduct $product): array
    {
        $bomLines = $product->bomLines()
            ->with(['materialItem', 'childSalesProduct', 'bomLineSuppliers.supplier'])
            ->get();

        $lines = [];

        foreach ($bomLines as $bomLine) {
            // 型態 B：子銷售產品，從其最新 PCF 快照取碳強度
            if ($bomLine->child_sales_product_id) {
                $childSnapshot = $bomLine->childSalesProduct?->latestPcfSnapshot();
                $emissionPerUnit = $childSnapshot ? (float) $childSnapshot->total_pcf : null;
                $quantity        = (float) ($bomLine->quantity ?? 1);
                $subtotal        = $emissionPerUnit !== null ? $emissionPerUnit * $quantity : null;

                $lines[] = [
                    'bom_line_id'           => $bomLine->id,
                    'child_sales_product_id' => $bomLine->child_sales_product_id,
                    'material_name'          => $bomLine->childSalesProduct?->name ?? '',
                    'hs_code'                => $bomLine->childSalesProduct?->hs_code,
                    'supplier_id'            => null,
                    'supplier_name'          => null,
                    'quantity'               => $quantity,
                    'unit'                   => $bomLine->unit ?? '件',
                    'emission_per_unit'      => $emissionPerUnit,
                    'subtotal'               => $subtotal,
                    'emission_source'        => 'child-pcf',
                    'is_estimated'           => false,
                    'is_flagged'             => false,
                    'reported_period'        => $childSnapshot?->snapshot_at?->toDateString(),
                    'data_quality'           => $emissionPerUnit !== null ? 'primary' : 'missing',
                ];
                continue;
            }

            // 型態 A：material_item_id
            $primarySupplierRel = $bomLine->bomLineSuppliers->firstWhere('role', 'primary');
            $emission           = null;
            $supplierId         = null;
            $supplierName       = null;

            if ($primarySupplierRel) {
                $supplierId   = $primarySupplierRel->supplier_id;
                $supplierName = $primarySupplierRel->supplier?->name;

                if ($bomLine->material_item_id) {
                    $emission = $this->emissionService->getBestEmissionForSupplier(
                        $bomLine->material_item_id,
                        $supplierId
                    );
                }
            }

            $emissionPerUnit = $emission ? (float) $emission->emissions_value : null;
            $quantity        = (float) ($bomLine->quantity ?? 1);
            $subtotal        = $emissionPerUnit !== null ? $emissionPerUnit * $quantity : null;

            $lines[] = [
                'bom_line_id'       => $bomLine->id,
                'material_item_id'  => $bomLine->material_item_id,
                'material_name'     => $bomLine->material_name ?? $bomLine->materialItem?->name,
                'hs_code'           => $bomLine->hs_code ?? $bomLine->materialItem?->hs_code,
                'supplier_id'       => $supplierId,
                'supplier_name'     => $supplierName,
                'quantity'          => $quantity,
                'unit'              => $bomLine->unit ?? '件',
                'emission_per_unit' => $emissionPerUnit,
                'subtotal'          => $subtotal,
                'emission_source'   => $emission?->source,
                'is_estimated'      => $emission?->is_estimated ?? false,
                'is_flagged'        => $emission?->is_flagged ?? false,
                'reported_period'   => $emission?->reported_period,
                'data_quality'      => $this->resolveDataQuality($emission?->source),
            ];
        }

        return $lines;
    }

    /**
     * append-only：每次呼叫建立新快照
     */
    public function snapshot(SalesProduct $product): PcfSnapshot
    {
        $result    = $this->calcForProduct($product);
        $pcrResult = $this->pcrService->calcForProduct($product);

        $snapshot = PcfSnapshot::create([
            'sales_product_id'     => $product->id,
            'total_pcf'            => $result['total_pcf'],
            'functional_unit'      => '件',
            'iso14067_ready'       => $result['iso14067_ready'],
            'lines'                => $result['lines'],
            'pcr_ratio'            => $pcrResult['pcr_ratio'],
            'pcr_incomplete_lines' => $pcrResult['incomplete_lines'],
        ]);

        return $snapshot;
    }

    private function resolveDataQuality(?string $source): string
    {
        return match ($source) {
            'portal-self'  => 'primary',
            'buyer-input'  => 'secondary',
            'ai-estimated' => 'estimated',
            default        => 'missing',
        };
    }
}
