<?php

namespace App\Services\ProductionBatch;

use App\Models\ProductionBatch;
use App\Models\SalesProduct;
use App\Models\Supplier;
use App\Models\SupplierComplianceDoc;
use App\Services\Chemical\HazardDisclosureService;
use App\Services\Compliance\ProductUpstreamResolver;
use App\Services\ProductionBatch\BatchProcessDueDiligenceService;

/**
 * 生產批號「Batch Passport」：把一個批次涉及的產品身分、碳足跡、循環經濟、
 * 合規文件、原料溯源、出口市場審查結果，彙總成一份穩定、唯讀的 JSON 契約。
 *
 * 跟 BatchExportReviewService::ddsDraft() 不同：ddsDraft 是「某一次出口審查的
 * 草稿快照」，這裡是「批次當下所有已知資料的完整彙總」，職責分開、互不依賴。
 *
 * v1 範圍內僅供內部 auth:api 認證使用者讀取；若未來要開放外部 DPP 註冊系統
 * 直接串接，需要另外設計 API Key／對外認證機制（不在本次範圍內）。
 */
class BatchPassportService
{
    public const SCHEMA_VERSION = '1.1';

    public function __construct(
        private readonly HazardDisclosureService $hazardService,
        private readonly ProductUpstreamResolver $upstream,
        private readonly BatchProcessDueDiligenceService $dueDiligenceService,
    ) {}

    public function build(ProductionBatch $batch): array
    {
        $batch->load([
            'supplier:id,name,code',
            'salesProduct',
            'salesProduct.pcfSnapshots',
            'salesProduct.circularitySnapshots',
            'salesProduct.packaging',
            'salesProduct.batterySpec',
            'salesProduct.bomLines.materialGroup',
            'salesProduct.bomLines.materialItem:id,name,microfiber_release_risk',
            'salesProduct.bomLines.materialItem.materialGroup',
            'salesProduct.bomLines.bomLineSuppliers',
            'salesProduct.bomLines.materialItem.approvedSuppliers',
            'rawMaterialOrigins',
            'exportReviews',
        ]);

        $product = $batch->salesProduct;

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at'   => now()->toISOString(),
            'batch'          => $this->buildBatch($batch),
            'product'        => $this->buildProduct($product),
            'carbon_footprint' => $this->buildCarbonFootprint($batch, $product),
            'circularity'      => $this->buildCircularity($product),
            'packaging'        => $this->buildPackaging($product),
            'battery_spec'     => $this->buildBatterySpec($product),
            'hazard_disclosure' => $product ? $this->hazardService->checkProduct($product) : null,
            'microfiber_release_risks' => $this->buildMicrofiberRisks($product),
            'compliance_documents' => $this->buildComplianceDocuments($batch, $product),
            'traceability'         => $this->buildTraceability($batch),
            'process_locations'    => $this->buildProcessLocations($batch, $product),
            'process_due_diligence' => $this->dueDiligenceService->build($batch, $product),
            'supply_chain_compliance' => $this->buildSupplyChainCompliance($batch, $product),
            'export_market_reviews' => $this->buildExportReviews($batch),
        ];
    }

    /**
     * 供應鏈合規調查清單：以 BOM 表為出發點，逐一物料列出「選擇供應商 → 溯源調查
     * → 合規調查」的完整鏈路，取代原本「合規文件」與「原料溯源」兩份互不關聯的
     * 平鋪清單。每一列都是同一個物料的完整脈絡，不會混雜其他物料的供應商文件。
     */
    private function buildSupplyChainCompliance(ProductionBatch $batch, ?SalesProduct $product): array
    {
        if (!$product) {
            return [];
        }

        $product->loadMissing([
            'bomLines.materialGroup',
            'bomLines.materialItem.materialGroup',
            'bomLines.materialItem.approvedSuppliers.supplier:id,name,code',
        ]);

        $originsByLine = $batch->rawMaterialOrigins->whereNotNull('bom_line_id')->keyBy('bom_line_id');

        // 一次查完本批次所有候選供應商的合規文件，避免逐物料重複查詢
        $candidateSupplierIds = $product->bomLines
            ->flatMap(function ($line) use ($originsByLine) {
                $origin = $originsByLine->get($line->id);
                if ($origin?->supplier_id) {
                    return [$origin->supplier_id];
                }
                return $line->materialItem?->approvedSuppliers->pluck('supplier_id') ?? [];
            })
            ->filter()
            ->unique()
            ->values();

        $docsBySupplier = SupplierComplianceDoc::whereIn('supplier_id', $candidateSupplierIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('supplier_id');

        return $product->bomLines->map(function ($line) use ($originsByLine, $docsBySupplier) {
            $group = $line->materialGroup ?? $line->materialItem?->materialGroup;
            $requiredDocTypes = $group?->required_doc_types ?? [];
            $origin = $originsByLine->get($line->id);

            // 1) 選擇供應商：批次已選定的實際供應商優先；未選定時退回物料核可清單的主要供應商（標示為「建議」）
            $selectedSupplierId = null;
            $isConfirmed = false;
            if ($origin?->supplier_id) {
                $selectedSupplierId = $origin->supplier_id;
                $isConfirmed = true;
            } else {
                $primary = $line->materialItem?->approvedSuppliers->firstWhere('role', 'primary')
                    ?? $line->materialItem?->approvedSuppliers->first();
                $selectedSupplierId = $primary?->supplier_id;
            }
            $selectedSupplier = $selectedSupplierId ? Supplier::find($selectedSupplierId) : null;

            // 2) 溯源調查：該物料在此批次的原料溯源紀錄
            $traceability = $origin ? [
                'origin_country'        => $origin->origin_country,
                'facility_name'         => $origin->facility_name,
                'gps'                   => ($origin->gps_lat && $origin->gps_lng) ? "{$origin->gps_lat}, {$origin->gps_lng}" : null,
                'harvest_year'          => $origin->harvest_year,
                'certification_ref'     => $origin->certification_ref,
                'transport_mode'        => $origin->transport_mode,
                'transport_distance_km' => $origin->transport_distance_km,
            ] : null;

            // 3) 合規調查：該供應商是否具備此物料類別要求的文件
            $docs = $selectedSupplierId ? ($docsBySupplier->get($selectedSupplierId) ?? collect()) : collect();
            $docStatuses = collect($requiredDocTypes)->map(function ($docType) use ($docs) {
                $doc = $docs->firstWhere('doc_type', $docType);
                return [
                    'doc_type'   => $docType,
                    'status'     => $doc?->status ?? 'missing',
                    'expires_at' => $doc?->expires_at?->toDateString(),
                ];
            })->values()->toArray();

            return [
                'bom_line_id'         => $line->id,
                'material_name'       => $line->materialItem?->name ?? $line->material_name,
                'required_doc_types'  => $requiredDocTypes,
                'selected_supplier'   => $selectedSupplier ? [
                    'id'   => $selectedSupplier->id,
                    'name' => $selectedSupplier->name,
                    'code' => $selectedSupplier->code,
                ] : null,
                'supplier_confirmed'  => $isConfirmed,
                'traceability'        => $traceability,
                'doc_statuses'        => $docStatuses,
            ];
        })->values()->toArray();
    }

    private function buildBatch(ProductionBatch $batch): array
    {
        return [
            'erp_batch_no'    => $batch->erp_batch_no,
            'erp_order_no'    => $batch->erp_order_no,
            'production_date' => $batch->production_date?->toDateString(),
            'quantity'        => (float) $batch->quantity,
            'unit'            => $batch->unit,
            'supplier'        => $batch->supplier ? [
                'name' => $batch->supplier->name,
                'code' => $batch->supplier->code,
            ] : null,
        ];
    }

    private function buildProduct(?\App\Models\SalesProduct $product): ?array
    {
        if (!$product) {
            return null;
        }

        return [
            'name'                   => $product->name,
            'product_code'           => $product->product_code,
            'model_no'               => $product->model_no,
            'hs_code'                => $product->hs_code,
            'applicable_regulations' => $product->applicable_regulations ?? [],
            'inferred_regulations'   => $product->inferred_regulations ?? [],
        ];
    }

    private function buildCarbonFootprint(ProductionBatch $batch, ?\App\Models\SalesProduct $product): array
    {
        $snapshot = $product?->pcfSnapshots?->sortByDesc('snapshot_at')->first();
        $isReady  = $snapshot?->iso14067_ready ?? false;

        return [
            'batch_lot_pcf'        => $batch->lot_pcf !== null ? (float) $batch->lot_pcf : null,
            'batch_lot_pcf_source' => $batch->lot_pcf_source,
            'product_total_pcf'    => $snapshot?->total_pcf,
            'functional_unit'      => $snapshot?->functional_unit,
            'iso14067_ready'       => $isReady,
            'iso14067_gap_reasons' => $isReady ? [] : $this->buildIso14067GapReasons($snapshot),
            'snapshot_at'          => $snapshot?->snapshot_at?->toISOString(),
        ];
    }

    /**
     * 「ISO 14067 就緒」為 false 時，逐一拆解 PcfSnapshot.lines 找出具體原因
     * （對應 PcfCalculationService::calcForProduct() 判定 iso14067_ready 的三個條件：
     * 每筆 BOM 行都要有排放係數、都不可是估算值/AI推估、且 BOM 不可為空）。
     */
    private function buildIso14067GapReasons(?\App\Models\PcfSnapshot $snapshot): array
    {
        if (!$snapshot) {
            return ['尚無 PCF 計算快照'];
        }

        $lines = $snapshot->lines ?? [];
        if (empty($lines)) {
            return ['產品尚無 BOM 物料，無法計算'];
        }

        $reasons = [];
        foreach ($lines as $line) {
            $name = $line['material_name'] ?? '未命名物料';
            if (($line['emission_per_unit'] ?? null) === null) {
                $reasons[] = "{$name}：缺排放係數";
            } elseif (($line['is_estimated'] ?? false) || ($line['emission_source'] ?? null) === 'ai-estimated') {
                $reasons[] = "{$name}：使用估算值/AI 推估，非實測數據";
            }
        }

        return $reasons;
    }

    private function buildCircularity(?\App\Models\SalesProduct $product): ?array
    {
        $snapshot = $product?->circularitySnapshots?->sortByDesc('calculated_at')->first();
        if (!$snapshot) {
            return null;
        }

        return [
            'recycled_content_ratio' => $snapshot->recycled_content_ratio,
            'pcr_ratio'              => $snapshot->pcr_ratio,
            'pir_ratio'              => $snapshot->pir_ratio,
            'bio_based_ratio'        => $snapshot->bio_based_ratio,
            'composition_breakdown'  => $snapshot->composition_breakdown ?? [],
            'recyclability_summary'  => $snapshot->recyclability_summary ?? [],
            'data_ready'             => $snapshot->data_ready,
            'calculated_at'          => $snapshot->calculated_at?->toISOString(),
        ];
    }

    /**
     * 合規文件範圍取該批次「實際選定」的供應商（batchSupplierIds），而非整個
     * 產品 BOM 全部上游供應商——批號→選供應商→合規調查，批次護照上的合規文件
     * 應反映這個批次實際用的是誰的文件，不是產品理論上所有可能供應商的文件。
     * 查詢方式仍比照 MarketComplianceChecker::check()：trade_good_id OR supplier_id
     * （supplier_compliance_docs.trade_good_id 實務上幾乎從未被填過，真正查得到
     * 資料的是 supplier_id 分支）。
     */
    private function buildComplianceDocuments(ProductionBatch $batch, ?\App\Models\SalesProduct $product): array
    {
        if (!$product) {
            return [];
        }

        $supplierIds = collect($this->upstream->batchSupplierIds($batch, $product));

        $docs = SupplierComplianceDoc::where(function ($q) use ($product, $supplierIds) {
            // trade_good_id 路徑保留但實務上幾乎未使用，主要資料流走 supplier_id
            $q->where('trade_good_id', $product->id);
            if ($supplierIds->isNotEmpty()) {
                $q->orWhereIn('supplier_id', $supplierIds);
            }
        })->with('supplier:id,name')->get();

        return $docs->map(fn ($doc) => [
            'doc_type'      => $doc->doc_type,
            'status'        => $doc->status,
            'issued_at'     => $doc->issued_at?->toDateString(),
            'expires_at'    => $doc->expires_at?->toDateString(),
            'supplier_name' => $doc->supplier?->name,
        ])->values()->toArray();
    }

    private function buildTraceability(ProductionBatch $batch): array
    {
        return [
            'origins' => $batch->rawMaterialOrigins->map(fn ($o) => [
                'material'               => $o->material_name,
                'country'                => $o->origin_country,
                'facility'               => $o->facility_name,
                'gps'                    => ($o->gps_lat && $o->gps_lng) ? "{$o->gps_lat}, {$o->gps_lng}" : null,
                'harvest_year'           => $o->harvest_year,
                'certification'          => $o->certification_ref,
                'transport_mode'         => $o->transport_mode,
                'transport_distance_km'  => $o->transport_distance_km,
            ])->values()->toArray(),
        ];
    }

    private function buildPackaging(?\App\Models\SalesProduct $product): ?array
    {
        $packaging = $product?->packaging;
        if (!$packaging) {
            return null;
        }

        return [
            'recycled_content_ratio' => $packaging->recycled_content_ratio,
            'recyclable'             => $packaging->recyclable,
            'reusable'               => $packaging->reusable,
            'material_description'   => $packaging->material_description,
        ];
    }

    /** 非電池類別產品（dpp_category !== 'battery'）一律回傳 null，比照 packaging 慣例 */
    private function buildBatterySpec(?\App\Models\SalesProduct $product): ?array
    {
        if (!$product || $product->dpp_category !== 'battery' || !$product->batterySpec) {
            return null;
        }

        $spec = $product->batterySpec;

        return [
            'battery_category' => $spec->battery_category,
            'chemistry'        => $spec->chemistry,
            'rated_capacity_ah' => $spec->rated_capacity_ah,
            'rated_voltage_v'   => $spec->rated_voltage_v,
            'weight_kg'         => $spec->weight_kg,
            'critical_materials' => [
                'lithium_recycled_content_ratio' => $spec->lithium_recycled_content_ratio,
                'cobalt_recycled_content_ratio'  => $spec->cobalt_recycled_content_ratio,
                'nickel_recycled_content_ratio'  => $spec->nickel_recycled_content_ratio,
                'lead_recycled_content_ratio'    => $spec->lead_recycled_content_ratio,
            ],
            'performance' => [
                'cycle_life'                 => $spec->cycle_life,
                'expected_lifetime_years'    => $spec->expected_lifetime_years,
                'discharge_efficiency_ratio' => $spec->discharge_efficiency_ratio,
                'initial_capacity_soh_note'  => $spec->initial_capacity_soh_note,
                'operating_temp_range'       => $spec->operating_temp_range,
            ],
        ];
    }

    private function buildMicrofiberRisks(?\App\Models\SalesProduct $product): array
    {
        if (!$product) {
            return [];
        }

        return $product->bomLines
            ->filter(fn ($line) => $line->materialItem && $line->materialItem->microfiber_release_risk !== 'not_rated')
            ->map(fn ($line) => [
                'material_name' => $line->materialItem->name,
                'risk'          => $line->materialItem->microfiber_release_risk,
            ])
            ->values()
            ->toArray();
    }

    /**
     * 批次層級：呈現該批次已選定的製程供應商（BatchProcessFacility），未選定的
     * 製程類型標示 confirmed: false 並列出候選供應商供前端選擇，不再直接等同
     * 產品層級全部核可供應商清單。見 batch-process-facility-selection design.md 決策 3。
     */
    private function buildProcessLocations(ProductionBatch $batch, ?SalesProduct $product): array
    {
        if (!$product) {
            return [];
        }

        return $this->upstream->batchProcessTypes($batch, $product)
            ->map(fn ($entry) => [
                'process_type'  => $entry['process_type'],
                'confirmed'     => $entry['confirmed'],
                'facility_name' => $entry['selected']['facility_name'] ?? null,
                'country'       => $entry['selected']['country'] ?? null,
                'supplier_name' => $entry['selected']['supplier_name'] ?? null,
                'candidates'    => $entry['candidates'],
            ])
            ->values()
            ->toArray();
    }

    private function buildExportReviews(ProductionBatch $batch): array
    {
        $product = $batch->salesProduct;

        return $batch->exportReviews->map(function ($r) use ($batch, $product) {
            $possiblyStale = $product
                ? $this->upstream->hasNewerComplianceDocsSince($batch, $product, $r->reviewed_at)
                : false;

            return [
                'market'         => $r->market,
                'program'        => $r->program,
                'status'         => $r->status,
                'reviewed_at'    => $r->reviewed_at?->toISOString(),
                'possibly_stale' => $possiblyStale,
                'findings'       => $r->findings ?? [],
            ];
        })->values()->toArray();
    }
}
