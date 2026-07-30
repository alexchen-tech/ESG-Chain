<?php

namespace App\Services\PCF;

use App\Models\PcfSnapshot;
use App\Models\SalesProduct;
use Illuminate\Support\Facades\Log;

class PcfCalculationService
{
    private PcrCalculationService $pcrService;

    public function __construct(
        private readonly MaterialEmissionService $emissionService,
        private readonly PcfAiClient $aiClient,
    ) {
        $this->pcrService = app(PcrCalculationService::class);
    }

    /**
     * 蒐集 BOM 行原始資料（取 primary supplier 最佳碳排、子產品最新 PCF），
     * 不計算 subtotal / total_pcf / iso14067_ready / data_quality——那些交給 esgchain-ai。
     * 輸出結構對應 esgchain-ai ProductPcfLineRequest。
     */
    public function buildLineRequests(SalesProduct $product): array
    {
        $bomLines = $product->bomLines()
            ->with(['materialItem', 'childSalesProduct', 'bomLineSuppliers.supplier'])
            ->get();

        $lines = [];

        foreach ($bomLines as $bomLine) {
            // 型態 B：子銷售產品，從其最新 PCF 快照取碳強度
            if ($bomLine->child_sales_product_id) {
                $childSnapshot   = $bomLine->childSalesProduct?->latestPcfSnapshot();
                $emissionPerUnit = $childSnapshot ? (float) $childSnapshot->total_pcf : null;
                $quantity        = (float) ($bomLine->quantity ?? 1);

                $lines[] = [
                    'bom_line_id'             => $bomLine->id,
                    'line_type'               => 'component',
                    'material_item_id'        => null,
                    'child_sales_product_id'  => $bomLine->child_sales_product_id,
                    'material_name'           => $bomLine->childSalesProduct?->name ?? '',
                    'hs_code'                 => $bomLine->childSalesProduct?->hs_code,
                    'supplier_id'             => null,
                    'supplier_name'           => null,
                    'quantity'                => $quantity,
                    'unit'                    => $bomLine->unit ?? '件',
                    'emission_per_unit'       => $emissionPerUnit,
                    'emission_source'         => 'child-pcf',
                    'is_estimated'            => false,
                    'is_flagged'              => false,
                    'reported_period'         => $childSnapshot?->snapshot_at?->toDateString(),
                    'net_weight'              => null,
                    'pcr_percentage'          => null,
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
            $item            = $bomLine->materialItem;

            $lines[] = [
                'bom_line_id'             => $bomLine->id,
                'line_type'               => 'material',
                'material_item_id'        => $bomLine->material_item_id,
                'child_sales_product_id'  => null,
                'material_name'           => $bomLine->material_name ?? $item?->name,
                'hs_code'                 => $bomLine->hs_code ?? $item?->hs_code,
                'supplier_id'             => $supplierId,
                'supplier_name'           => $supplierName,
                'quantity'                => $quantity,
                'unit'                    => $bomLine->unit ?? '件',
                'emission_per_unit'       => $emissionPerUnit,
                'emission_source'         => $emission?->source,
                'is_estimated'            => $emission?->is_estimated ?? false,
                'is_flagged'              => $emission?->is_flagged ?? false,
                'reported_period'         => $emission?->reported_period,
                'net_weight'              => $item?->net_weight,
                'pcr_percentage'          => $item?->pcr_percentage,
            ];
        }

        return $lines;
    }

    /**
     * 舊版本機計算（型態 A/B 皆保留原邏輯），僅用於與 AI 回傳結果交叉驗證，不再是正式資料來源。
     */
    protected function legacyCalc(SalesProduct $product): array
    {
        $lineRequests = $this->buildLineRequests($product);
        $totalPcf     = 0.0;
        $iso14067Ready = true;
        $lines        = [];

        foreach ($lineRequests as $line) {
            $emissionPerUnit = $line['emission_per_unit'];
            $subtotal        = $emissionPerUnit !== null ? $emissionPerUnit * $line['quantity'] : null;

            if ($emissionPerUnit !== null) {
                $totalPcf += $subtotal;
            } else {
                $iso14067Ready = false;
            }

            if ($line['is_estimated'] || $line['emission_source'] === 'ai-estimated') {
                $iso14067Ready = false;
            }

            $line['subtotal']     = $subtotal;
            $line['data_quality'] = $this->legacyDataQuality($line);
            $lines[]              = $line;
        }

        return [
            'total_pcf'      => $totalPcf > 0 ? $totalPcf : null,
            'iso14067_ready' => $iso14067Ready && count($lines) > 0,
            'lines'          => $lines,
        ];
    }

    private function legacyDataQuality(array $line): string
    {
        if ($line['emission_source'] === 'child-pcf') {
            return $line['emission_per_unit'] !== null ? 'primary' : 'missing';
        }

        return match ($line['emission_source']) {
            'portal-self'  => 'primary',
            'buyer-input'  => 'secondary',
            'ai-estimated' => 'estimated',
            default        => 'missing',
        };
    }

    /**
     * append-only：每次呼叫建立新快照。實際計算改由 esgchain-ai 執行
     * （CLAUDE.md：計算邏輯不可寫在 esgchain-api），legacyCalc() 僅作交叉驗證用。
     */
    public function snapshot(SalesProduct $product): PcfSnapshot
    {
        $lineRequests = $this->buildLineRequests($product);

        $aiResult = $this->aiClient->calculate($product->id, '件', $lineRequests);

        $legacyResult = $this->legacyCalc($product);
        $legacyPcr    = $this->pcrService->calcForProduct($product);

        $this->crossValidate($product, $aiResult, $legacyResult, $legacyPcr);

        $snapshot = PcfSnapshot::create([
            'sales_product_id'     => $product->id,
            'total_pcf'            => $aiResult['total_pcf'] ?? null,
            'functional_unit'      => $aiResult['functional_unit'] ?? '件',
            'iso14067_ready'       => $aiResult['iso14067_ready'] ?? false,
            'lines'                => $aiResult['lines'] ?? [],
            'pcr_ratio'            => $aiResult['pcr_ratio'] ?? null,
            'pcr_incomplete_lines' => $aiResult['pcr_incomplete_lines'] ?? null,
        ]);

        return $snapshot;
    }

    private function crossValidate(SalesProduct $product, array $aiResult, array $legacyResult, array $legacyPcr): void
    {
        $tolerance = 0.0001;

        $newTotal = $aiResult['total_pcf'] ?? null;
        $oldTotal = $legacyResult['total_pcf'] ?? null;
        $totalDiffers = ($newTotal === null) !== ($oldTotal === null)
            || ($newTotal !== null && $oldTotal !== null && abs($newTotal - $oldTotal) > $tolerance);

        $newReady = (bool) ($aiResult['iso14067_ready'] ?? false);
        $oldReady = (bool) ($legacyResult['iso14067_ready'] ?? false);

        $newPcr = $aiResult['pcr_ratio'] ?? null;
        $oldPcr = $legacyPcr['pcr_ratio'] ?? null;
        $pcrDiffers = ($newPcr === null) !== ($oldPcr === null)
            || ($newPcr !== null && $oldPcr !== null && abs($newPcr - $oldPcr) > $tolerance);

        if ($totalDiffers || $newReady !== $oldReady || $pcrDiffers) {
            Log::warning('PCF AI 計算結果與舊版本機計算不一致', [
                'sales_product_id' => $product->id,
                'new'              => [
                    'total_pcf'      => $newTotal,
                    'iso14067_ready' => $newReady,
                    'pcr_ratio'      => $newPcr,
                ],
                'old'              => [
                    'total_pcf'      => $oldTotal,
                    'iso14067_ready' => $oldReady,
                    'pcr_ratio'      => $oldPcr,
                ],
            ]);
        }
    }
}
