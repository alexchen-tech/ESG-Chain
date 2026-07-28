<?php

namespace App\Services\ProductionBatch;

use App\Models\ProductionBatch;
use App\Models\RiskAssessment;
use App\Models\SalesProduct;
use App\Models\Supplier;
use App\Services\Compliance\ProductUpstreamResolver;

/**
 * 批次已選定的製程供應商 × 既有 SAQ 六維風險評分 串接查詢。
 *
 * 不新增評分邏輯或資料表，完全重用 Supplier::latestRiskAssessment() 與
 * RiskAssessment::dimToLevel()。純讀取，不影響 gateCheck()/出口審查。
 * 見 openspec/changes/process-due-diligence-saq-linkage/design.md。
 */
class BatchProcessDueDiligenceService
{
    /** 製程類型 → 六維風險構面對應。其餘製程類型（一般製造/倉儲/辦公室等）不觸發此檢查。 */
    public const PROCESS_RISK_DIM_MAP = [
        'dyeing'            => 'dim_e1', // 染整：環境管理
        'wet_processing'     => 'dim_e1', // 濕製程：環境管理
        'printing'           => 'dim_e1', // 印花：環境管理
        'garment_assembly'   => 'dim_e3', // 成衣縫製：社會責任
    ];

    private const DIMENSION_LABELS = [
        'dim_e1' => '環境管理',
        'dim_e3' => '社會責任',
    ];

    public function __construct(
        private readonly ProductUpstreamResolver $upstream,
    ) {}

    public function build(ProductionBatch $batch, ?SalesProduct $product): array
    {
        if (!$product) {
            return [];
        }

        $processTypes = $this->upstream->batchProcessTypes($batch, $product)
            ->filter(fn ($entry) => array_key_exists($entry['process_type'], self::PROCESS_RISK_DIM_MAP));

        return $processTypes->map(function ($entry) {
            $dimension = self::PROCESS_RISK_DIM_MAP[$entry['process_type']];
            $dimensionLabel = self::DIMENSION_LABELS[$dimension];

            $result = [
                'process_type'    => $entry['process_type'],
                'dimension'       => $dimension,
                'dimension_label' => $dimensionLabel,
                'status'          => 'pending_selection',
                'risk_level'      => null,
                'score'           => null,
                'supplier_id'     => null,
                'supplier_name'   => null,
            ];

            if (!$entry['confirmed'] || !$entry['selected']) {
                return $result;
            }

            $supplierId = $entry['selected']['supplier_id'];
            $result['supplier_id'] = $supplierId;
            $result['supplier_name'] = $entry['selected']['supplier_name'];

            $supplier = Supplier::find($supplierId);
            $assessment = $supplier?->latestRiskAssessment;
            $score = $assessment?->{$dimension};

            if (!$assessment || $score === null) {
                $result['status'] = 'not_assessed';
                return $result;
            }

            $result['status'] = 'assessed';
            $result['score'] = $score;
            $result['risk_level'] = RiskAssessment::dimToLevel($score);

            return $result;
        })->values()->toArray();
    }
}
