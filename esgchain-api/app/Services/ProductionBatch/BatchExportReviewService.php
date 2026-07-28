<?php

namespace App\Services\ProductionBatch;

use App\Models\BatchExportReview;
use App\Models\MaterialGroup;
use App\Models\ProductBomLine;
use App\Models\ProductionBatch;
use App\Models\SalesProduct;
use App\Models\TradeGood;
use App\Services\Chemical\HazardDisclosureService;
use App\Services\Compliance\MarketComplianceChecker;
use App\Services\Compliance\ProductUpstreamResolver;

/**
 * 批號×市場出口合規審查引擎。
 *
 * 依目標市場的永續產品規範整合資料面：
 *   1. 文件規則（market_compliance_rules × 供應商文件，重用 MarketComplianceChecker）
 *   2. EUDR 溯源（EU）：管制原料（木漿/天然橡膠）須具 GPS 地塊座標與收穫年
 *   3. UFLPA 佐證（US）：棉質原料須具產地國（非 CN）與認證編號
 *   4. 批次 PCF：lot_pcf 缺失 → warning
 *   5. DPP 欄位完備度（EU，六項 EU 紡織品 DPP 最小強制類別）：
 *      有害物質揭露 / 微纖維釋放風險 / 包材資訊 / 供應鏈製程級地點 / 再生料比例與可回收性 / 運輸資訊
 *
 * 屬規則檢核（非計分計算），依 MarketComplianceChecker 先例實作於 esgchain-api。
 */
class BatchExportReviewService
{
    public function __construct(
        private readonly MarketComplianceChecker $checker,
        private readonly HazardDisclosureService $hazardService,
        private readonly ProductUpstreamResolver $upstream,
    ) {}

    /**
     * 出貨關卡查詢：取該批次×市場「已存在」的審查結論，供出貨分配時做通行判斷。
     * 不主動觸發重新審查（審查是有意識的操作），僅讀取最近一次結果。
     *
     * status：pass/warning/fail（已審查）｜missing（尚未審查過此市場）
     * blocked：僅 fail 視為阻擋，其餘（含 missing）為警示不擋。
     * findings：一律回傳陣列，missing 時補一筆說明項，確保前端可統一渲染逐項清單。
     */
    public function gateCheck(ProductionBatch $batch, string $market): array
    {
        $review = BatchExportReview::where('production_batch_id', $batch->id)
            ->where('market', $market)
            ->first();

        if (!$review) {
            return [
                'market'      => $market,
                'status'      => 'missing',
                'blocked'     => false,
                'reviewed_at' => null,
                'findings'    => [$this->finding(
                    'no_review',
                    '尚未執行出口市場審查',
                    'warning',
                    "此批次尚未執行 {$market} 市場審查，建議先於生產批號頁面執行審查",
                )],
            ];
        }

        return [
            'market'         => $review->market,
            'program'        => $review->program,
            'status'         => $review->status,
            'blocked'        => $review->status === 'fail',
            'reviewed_at'    => $review->reviewed_at?->toISOString(),
            'possibly_stale' => $this->isPossiblyStale($batch, $review->reviewed_at),
            'findings'       => $review->findings ?? [],
        ];
    }

    /**
     * 該批次的各市場審查紀錄，附上 possibly_stale 提示——審查是靜態快照，供應商文件
     * 後續變動（如過期、補件）不會自動讓審查結果失效，需要提示使用者「這筆審查可能
     * 已經不新鮮」，而不是讓畫面一直顯示審查當下的舊結論。
     */
    public function listWithStaleness(ProductionBatch $batch)
    {
        $reviews = BatchExportReview::where('production_batch_id', $batch->id)
            ->orderBy('market')->get();

        return $reviews->map(function (BatchExportReview $review) use ($batch) {
            $review->setAttribute('possibly_stale', $this->isPossiblyStale($batch, $review->reviewed_at));
            return $review;
        });
    }

    /**
     * 審查完成後，若該批次相關供應商的合規文件有任何更新，視為「可能已過期」——
     * 不主動重跑審查（審查是有意識的操作），只提示使用者該重新確認。
     */
    private function isPossiblyStale(ProductionBatch $batch, $reviewedAt): bool
    {
        $product = SalesProduct::find($batch->sales_product_id);
        if (!$product) {
            return false;
        }

        return $this->upstream->hasNewerComplianceDocsSince($batch, $product, $reviewedAt);
    }

    /**
     * 產出單一批次的出口合規文件草稿（EUDR DDS 等），單批次視角，不做跨批集貨聚合。
     * 出口交易執行（客戶綁定、實際送件、報關）屬 ERP 範疇，不在此系統內。
     */
    public function ddsDraft(ProductionBatch $batch, string $market): array
    {
        $batch->load(['supplier:id,name,code', 'salesProduct:id,name,product_code,hs_code,model_no', 'rawMaterialOrigins']);

        $origins = $batch->rawMaterialOrigins->map(fn($o) => [
            'material'      => $o->material_name,
            'country'       => $o->origin_country,
            'facility'      => $o->facility_name,
            'gps'           => ($o->gps_lat && $o->gps_lng) ? "{$o->gps_lat}, {$o->gps_lng}" : null,
            'harvest_year'  => $o->harvest_year,
            'certification' => $o->certification_ref,
        ])->values()->toArray();

        $gate = $this->gateCheck($batch, $market);

        return [
            'erp_batch_no'          => $batch->erp_batch_no,
            'market'                => $market,
            'supplier'              => $batch->supplier?->name,
            'trade_good_code'       => $batch->salesProduct?->product_code,
            'trade_good_name'       => $batch->salesProduct?->name,
            'hs_code'               => $batch->salesProduct?->hs_code,
            'quantity'              => (float) $batch->quantity,
            'unit'                  => $batch->unit,
            'lot_pcf'               => $batch->lot_pcf !== null ? (float) $batch->lot_pcf : null,
            'lot_pcf_source'        => $batch->lot_pcf_source,
            'raw_material_origins'  => $origins,
            'origins_missing'       => empty($origins),
            'export_review'         => $gate,
            'generated_at'          => now()->toISOString(),
        ];
    }

    /**
     * 執行審查並 upsert 該批次×市場的審查紀錄。
     *
     * $program：僅執行該法規範疇（見 MarketComplianceRule::PROGRAMS）的檢查項目，
     * 未指定時（null）執行完整審查（涵蓋全部範疇），維持既有行為。指定範疇後只會
     * 跑該範疇對應的檢查方法，其餘檢查項目不執行——PCF 是基礎資料完整度檢查，
     * 不屬於特定法規範疇，一律執行。
     */
    public function review(ProductionBatch $batch, string $market, ?string $program = null): BatchExportReview
    {
        $product  = SalesProduct::find($batch->sales_product_id);
        $findings = [];

        if (!$product) {
            $findings[] = $this->finding('batch_link', '批次未綁定產品', 'fail', '此批次無所屬銷售產品，無法審查');
        } else {
            $runAll = $program === null;

            $findings = array_merge(
                $findings,
                // cbam 範疇目前僅檢查文件齊備度（CBAM_REPORT，見 checkMarketDocs），無專屬
                // 欄位完整度檢查（如碳含量計算方式、預設值使用揭露等），為目前功能邊界，非遺漏
                $this->checkMarketDocs($batch, $product, $market, $program),
                ($market === 'EU' && ($runAll || $program === 'eudr')) ? $this->checkEudrOrigins($batch, $product) : [],
                ($market === 'US' && ($runAll || $program === 'uflpa')) ? $this->checkUflpaOrigins($batch, $product) : [],
                $this->checkBatchPcf($batch),
                ($market === 'EU' && ($runAll || $program === 'dpp')) ? $this->checkDppFields($batch, $product) : [],
                ($market === 'EU' && $product->dpp_category === 'battery' && ($runAll || $program === 'dpp')) ? $this->checkBatteryDppFields($product) : [],
            );
        }

        $status = $this->overallStatus($findings);

        return BatchExportReview::updateOrCreate(
            ['production_batch_id' => $batch->id, 'market' => $market],
            ['program' => $program, 'status' => $status, 'findings' => $findings, 'reviewed_at' => now()],
        );
    }

    /**
     * 1) 市場文件規則（產品×批次實際選定供應商文件）。
     * 批號→選供應商→合規調查：供應商範圍取該批次 raw_material_origins 已選定
     * 的實際供應商（未選定的物料才退回產品 BOM 核可清單），而非籠統套用整個
     * 產品的全部上游供應商。
     * 全部必備文件皆 valid 時回傳單一摘要 finding；否則對每一份未達合規狀態的文件
     * 各自產生一筆結構化 finding（帶 doc_type/supplier_id 等，供前端連去補件頁面）。
     */
    private function checkMarketDocs(ProductionBatch $batch, SalesProduct $product, string $market, ?string $program = null): array
    {
        $supplierIds = $this->upstream->batchSupplierIds($batch, $product);
        $result = $this->checker->check(TradeGood::find($product->id), $market, $supplierIds, $program);
        $bad = collect($result['results'] ?? [])
            ->filter(fn($r) => in_array($r['status'], ['missing', 'expiring_soon', 'expired'], true));

        if ($bad->isEmpty()) {
            return [$this->finding('market_docs', '市場必備文件', 'pass', '必備文件齊備')];
        }

        $statusLabel = ['missing' => '缺失', 'expiring_soon' => '即將到期', 'expired' => '已過期'];
        $findingStatus = ['missing' => 'fail', 'expired' => 'fail', 'expiring_soon' => 'warning'];

        return $bad->map(function (array $r) use ($statusLabel, $findingStatus) {
            $detail = $statusLabel[$r['status']] ?? $r['status'];
            if ($r['expires_at']) {
                $detail .= "（到期日 {$r['expires_at']}）";
            }

            return [
                'check'         => "market_doc:{$r['doc_type']}",
                'label'         => $r['doc_type'],
                'status'        => $findingStatus[$r['status']] ?? 'warning',
                'detail'        => $detail,
                'doc_type'      => $r['doc_type'],
                'doc_status'    => $r['status'],
                'expires_at'    => $r['expires_at'],
                'supplier_id'   => $r['supplier_id'] ?? null,
                'supplier_name' => $r['supplier_name'] ?? null,
            ];
        })->values()->toArray();
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

    /**
     * 5) DPP 欄位完備度（EU ESPR 紡織品 DPP 最小強制六類）。
     * 資料粒度提醒：六項檢查中「有害物質揭露／微纖維釋放風險／包材資訊／供應鏈製程級
     * 地點／再生料比例與可回收性」共 5 項讀取的是產品層級（SalesProduct 本身或其快照
     * 關聯），只反映產品主檔目前狀態，與這個批次實際選用的供應商/原料無關；只有「運輸
     * 資訊」1 項是讀批次層級（此批次的 RawMaterialOrigin）。不要誤用批次資料覆蓋前 5 項
     * 的產品資料，或反過來拿這 5 項結果當作批次專屬判斷。
     */
    private function checkDppFields(ProductionBatch $batch, SalesProduct $product): array
    {
        return [
            $this->checkHazardDisclosure($product),
            $this->checkMicrofiberRisk($product),
            $this->checkPackagingInfo($product),
            $this->checkProcessLocation($product),
            $this->checkCircularityData($product),
            $this->checkTransportInfo($batch),
        ];
    }

    // 產品層級：讀取 SalesProduct 的有害物質檢核結果，與批次實際供應商無關
    private function checkHazardDisclosure(SalesProduct $product): array
    {
        $result = $this->hazardService->checkProduct($product);

        return $result['has_hazardous_substance']
            ? $this->finding('dpp_hazard', '有害物質揭露', 'warning', '存在未解除的有害物質警示，需揭露')
            : $this->finding('dpp_hazard', '有害物質揭露', 'pass', '無未解除的有害物質警示');
    }

    // 產品層級：讀取產品 BOM 各物料的微纖維釋放風險填報狀態，與批次無關
    private function checkMicrofiberRisk(SalesProduct $product): array
    {
        $rated = ProductBomLine::where('sales_product_id', $product->id)
            ->whereHas('materialItem', fn($q) => $q->where('microfiber_release_risk', '!=', 'not_rated'))
            ->exists();

        return $rated
            ? $this->finding('dpp_microfiber', '塑膠微纖維釋放風險', 'pass', '至少一項物料已填報微纖維釋放風險')
            : $this->finding('dpp_microfiber', '塑膠微纖維釋放風險', 'warning', '尚未填報任何物料的微纖維釋放風險');
    }

    // 產品層級：讀取產品是否已填寫包材資訊，與批次無關
    private function checkPackagingInfo(SalesProduct $product): array
    {
        $packaging = $product->packaging()->exists();

        return $packaging
            ? $this->finding('dpp_packaging', '包材資訊', 'pass', '此產品已填寫包材資訊')
            : $this->finding('dpp_packaging', '包材資訊', 'warning', '此產品尚未填寫包材資訊');
    }

    // 產品層級：讀取產品 BOM 關聯的物料供應商是否已指定製程廠區，與批次實際供應商無關
    private function checkProcessLocation(SalesProduct $product): array
    {
        $materialItemIds = ProductBomLine::where('sales_product_id', $product->id)
            ->pluck('material_item_id')
            ->filter();

        $hasFacility = \App\Models\MaterialItemSupplier::whereIn('material_item_id', $materialItemIds)
            ->whereNotNull('supplier_facility_id')
            ->exists();

        return $hasFacility
            ? $this->finding('dpp_process_location', '供應鏈製程級地點', 'pass', '至少一項上游關聯已指定製程廠區')
            : $this->finding('dpp_process_location', '供應鏈製程級地點', 'warning', '尚未指定任何製程廠區（織布/針織/染整/印花/濕製程/成衣製造）');
    }

    // 產品層級：讀取 SalesProduct 最新一筆循環經濟快照（latestCircularitySnapshot），與批次無關
    private function checkCircularityData(SalesProduct $product): array
    {
        $snapshot = $product->circularitySnapshots()->latest('calculated_at')->first();

        return ($snapshot && $snapshot->data_ready)
            ? $this->finding('dpp_circularity', '再生料比例與可回收性', 'pass', '此產品循環經濟快照資料完整')
            : $this->finding('dpp_circularity', '再生料比例與可回收性', 'warning', '此產品循環經濟快照尚未完整（缺再生料比例或可回收性資料）');
    }

    // 批次層級：唯一一項讀取批次實際資料的 DPP 檢查（此批次的 RawMaterialOrigin）
    private function checkTransportInfo(ProductionBatch $batch): array
    {
        $hasTransport = $batch->rawMaterialOrigins()->whereNotNull('transport_mode')->exists();

        return $hasTransport
            ? $this->finding('dpp_transport', '運輸資訊', 'pass', '至少一筆原料溯源已填寫運輸方式')
            : $this->finding('dpp_transport', '運輸資訊', 'warning', '原料溯源尚未填寫運輸方式/距離');
    }

    /**
     * 6) 電池 DPP 欄位完備度（EU 電池法規 (EU) 2023/1542）：僅 dpp_category=battery
     * 的產品觸發。跟 checkDppFields() 平行、不合併——電池三大類別（分類與化學系統／
     * 關鍵原料回收含量／效能耐久性）跟紡織品六大類別是完全不同的欄位群。
     * 資料粒度提醒：底下三項全部讀取產品層級（SalesProduct->batterySpec），與批次無關。
     */
    private function checkBatteryDppFields(SalesProduct $product): array
    {
        return [
            $this->checkBatterySpecCompleteness($product),
            $this->checkBatteryCriticalMaterials($product),
            $this->checkBatteryPerformance($product),
        ];
    }

    // 產品層級：讀取產品的 batterySpec，與批次無關
    private function checkBatterySpecCompleteness(SalesProduct $product): array
    {
        $spec = $product->batterySpec;
        $complete = $spec && $spec->battery_category && $spec->chemistry
            && $spec->rated_capacity_ah !== null && $spec->rated_voltage_v !== null && $spec->weight_kg !== null;

        return $this->finding(
            'battery_spec',
            '電池類別與化學系統',
            $complete ? 'pass' : 'warning',
            $complete ? '此產品電池類別、化學系統、容量、電壓、重量皆已填寫' : '此產品尚未完整填寫電池規格（類別/化學系統/容量/電壓/重量）',
        );
    }

    // 產品層級：讀取產品的 batterySpec，與批次無關
    private function checkBatteryCriticalMaterials(SalesProduct $product): array
    {
        $spec = $product->batterySpec;
        $hasAny = $spec && collect([
            $spec->lithium_recycled_content_ratio,
            $spec->cobalt_recycled_content_ratio,
            $spec->nickel_recycled_content_ratio,
            $spec->lead_recycled_content_ratio,
        ])->contains(fn ($v) => $v !== null);

        return $this->finding(
            'battery_critical_materials',
            '關鍵原料回收含量',
            $hasAny ? 'pass' : 'warning',
            $hasAny ? '此產品已填寫至少一項鋰/鈷/鎳/鉛回收料含量' : '此產品尚未填寫鋰/鈷/鎳/鉛任一項回收料含量',
        );
    }

    // 產品層級：讀取產品的 batterySpec，與批次無關
    private function checkBatteryPerformance(SalesProduct $product): array
    {
        $spec = $product->batterySpec;
        $complete = $spec && $spec->cycle_life !== null && $spec->expected_lifetime_years !== null;

        return $this->finding(
            'battery_performance',
            '效能與耐久性',
            $complete ? 'pass' : 'warning',
            $complete ? '此產品循環壽命與預期使用年限已填寫' : '此產品尚未填寫循環壽命/預期使用年限等效能耐久性欄位',
        );
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
