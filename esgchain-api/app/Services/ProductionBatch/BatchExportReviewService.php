<?php

namespace App\Services\ProductionBatch;

use App\Models\BatchExportReview;
use App\Models\MaterialGroup;
use App\Models\ProductBomLine;
use App\Models\ProductionBatch;
use App\Models\SalesProduct;
use App\Models\TradeGood;
use App\Services\Compliance\MarketComplianceChecker;

/**
 * 批號×市場出口合規審查引擎。
 *
 * 依目標市場的永續產品規範整合資料面：
 *   1. 文件規則（market_compliance_rules × 供應商文件，重用 MarketComplianceChecker）
 *   2. EUDR 溯源（EU）：管制原料（木漿/天然橡膠）須具 GPS 地塊座標與收穫年
 *   3. UFLPA 佐證（US）：棉質原料須具產地國（非 CN）與認證編號
 *   4. 批次 PCF：lot_pcf 缺失 → warning
 *   5. DPP 欄位完備度（EU）：model_no / hs_code / embedded_emissions
 *
 * 屬規則檢核（非計分計算），依 MarketComplianceChecker 先例實作於 esgchain-api。
 */
class BatchExportReviewService
{
    public function __construct(private readonly MarketComplianceChecker $checker) {}

    /** 執行審查並 upsert 該批次×市場的審查紀錄。 */
    public function review(ProductionBatch $batch, string $market): BatchExportReview
    {
        $product  = SalesProduct::find($batch->sales_product_id);
        $findings = [];

        if (!$product) {
            $findings[] = $this->finding('batch_link', '批次未綁定產品', 'fail', '此批次無所屬銷售產品，無法審查');
        } else {
            $findings = array_merge(
                $findings,
                $this->checkMarketDocs($product, $market),
                $market === 'EU' ? $this->checkEudrOrigins($batch, $product) : [],
                $market === 'US' ? $this->checkUflpaOrigins($batch, $product) : [],
                $this->checkBatchPcf($batch),
                $market === 'EU' ? $this->checkDppFields($product) : [],
            );
        }

        $status = $this->overallStatus($findings);

        return BatchExportReview::updateOrCreate(
            ['production_batch_id' => $batch->id, 'market' => $market],
            ['status' => $status, 'findings' => $findings, 'reviewed_at' => now()],
        );
    }

    /** 1) 市場文件規則（產品×上游供應商文件） */
    private function checkMarketDocs(SalesProduct $product, string $market): array
    {
        $result  = $this->checker->check(TradeGood::find($product->id), $market);
        $overall = $result['overall'] ?? 'pass';

        $missing = collect($result['results'] ?? [])
            ->filter(fn($r) => in_array($r['status'], ['missing', 'expired'], true))
            ->pluck('doc_type')->implode('、');

        $status = match ($overall) {
            'fail'    => 'fail',
            'warning' => 'warning',
            default   => 'pass',
        };

        return [$this->finding(
            'market_docs',
            '市場必備文件',
            $status,
            $status === 'pass' ? '必備文件齊備' : "缺失/過期：{$missing}",
        )];
    }

    /** 2) EUDR 溯源：管制原料須具 GPS 與收穫年 */
    private function checkEudrOrigins(ProductionBatch $batch, SalesProduct $product): array
    {
        $eudrLineIds = $this->linesRequiringDoc($product->id, 'EUDR_DDS');
        if ($eudrLineIds->isEmpty()) {
            return [$this->finding('eudr_origins', 'EUDR 原料溯源', 'pass', '本產品無 EUDR 管制原料')];
        }

        $origins = $batch->rawMaterialOrigins->whereIn('bom_line_id', $eudrLineIds->all());
        if ($origins->isEmpty()) {
            return [$this->finding('eudr_origins', 'EUDR 原料溯源', 'fail', 'EUDR 管制原料（木漿/橡膠等）無批次溯源紀錄')];
        }

        $bad = $origins->filter(fn($o) => !$o->gps_lat || !$o->gps_lng || !$o->harvest_year);
        if ($bad->isNotEmpty()) {
            $names = $bad->pluck('material_name')->implode('、');
            return [$this->finding('eudr_origins', 'EUDR 原料溯源', 'fail', "缺 GPS 地塊座標或收穫年：{$names}")];
        }

        return [$this->finding('eudr_origins', 'EUDR 原料溯源', 'pass', '管制原料皆具地塊座標與收穫年')];
    }

    /** 3) UFLPA：棉質原料須具產地國（非 CN）與認證編號 */
    private function checkUflpaOrigins(ProductionBatch $batch, SalesProduct $product): array
    {
        $cottonLineIds = $this->linesRequiringDoc($product->id, 'UFLPA_DECLARATION');
        if ($cottonLineIds->isEmpty()) {
            return [$this->finding('uflpa_origins', 'UFLPA 棉花溯源', 'pass', '本產品無 UFLPA 敏感原料')];
        }

        $origins = $batch->rawMaterialOrigins->whereIn('bom_line_id', $cottonLineIds->all());
        if ($origins->isEmpty()) {
            return [$this->finding('uflpa_origins', 'UFLPA 棉花溯源', 'fail', '棉質原料無批次溯源紀錄，無法佐證產地')];
        }

        if ($origins->contains(fn($o) => strtoupper((string) $o->origin_country) === 'CN')) {
            return [$this->finding('uflpa_origins', 'UFLPA 棉花溯源', 'fail', '溯源含中國產地，UFLPA 推定禁止進口')];
        }

        $noCert = $origins->filter(fn($o) => empty($o->certification_ref));
        if ($noCert->isNotEmpty()) {
            return [$this->finding('uflpa_origins', 'UFLPA 棉花溯源', 'warning', '產地認證編號缺失，佐證力不足')];
        }

        return [$this->finding('uflpa_origins', 'UFLPA 棉花溯源', 'pass', '產地與認證佐證齊備')];
    }

    /** 4) 批次 PCF */
    private function checkBatchPcf(ProductionBatch $batch): array
    {
        return [$batch->lot_pcf !== null
            ? $this->finding('batch_pcf', '批次碳足跡', 'pass', "lot PCF = {$batch->lot_pcf} kgCO2e（{$batch->lot_pcf_source}）")
            : $this->finding('batch_pcf', '批次碳足跡', 'warning', '批次 PCF 未計算')];
    }

    /** 5) DPP 欄位完備度（EU ESPR） */
    private function checkDppFields(SalesProduct $product): array
    {
        $missing = [];
        if (!$product->model_no)                    $missing[] = '型號';
        if (!$product->hs_code)                     $missing[] = 'HS Code';
        if ($product->embedded_emissions === null)  $missing[] = '內含碳排';

        return [empty($missing)
            ? $this->finding('dpp_fields', 'DPP 欄位完備度', 'pass', '型號/HS/內含碳排齊備')
            : $this->finding('dpp_fields', 'DPP 欄位完備度', 'warning', '缺：' . implode('、', $missing))];
    }

    /** 產品 BOM 中，其物料群組要求特定文件（EUDR_DDS/UFLPA_DECLARATION）的 BOM 行 id */
    private function linesRequiringDoc(string $productId, string $docType)
    {
        $groupIds = MaterialGroup::all()
            ->filter(fn($g) => in_array($docType, $g->required_doc_types ?? [], true))
            ->pluck('id');

        return ProductBomLine::where('sales_product_id', $productId)
            ->whereIn('material_group_id', $groupIds)
            ->pluck('id');
    }

    private function overallStatus(array $findings): string
    {
        $statuses = array_column($findings, 'status');
        if (in_array('fail', $statuses, true))    return 'fail';
        if (in_array('warning', $statuses, true)) return 'warning';
        return 'pass';
    }

    private function finding(string $check, string $label, string $status, string $detail): array
    {
        return compact('check', 'label', 'status', 'detail');
    }
}
