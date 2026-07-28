<?php

namespace App\Http\Controllers\Api\TradeGoods;

use App\Http\Controllers\Controller;
use App\Models\BomLineSupplier;
use App\Models\MarketComplianceRule;
use App\Models\ProductBomLine;
use App\Models\TradeGood;
use App\Services\Compliance\MarketComplianceChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeGoodMarketComplianceController extends Controller
{
    private const DOC_TYPE_LABELS = [
        'UFLPA_DECLARATION' => 'UFLPA 聲明',
        'ORIGIN_CERT'       => '原產地證書',
        'EUDR_DDS'          => 'EUDR 盡職調查',
        'REACH_DECLARATION' => 'REACH 聲明',
        'ROHS_DECLARATION'  => 'RoHS 聲明',
        'CONFLICT_MINERALS' => '衝突礦產報告',
        'SUPPLIER_COC'      => '供應商行為準則',
        'PCF_REPORT'        => 'PCF 報告',
        'TEST_REPORT'       => '測試報告',
        'AUDIT_CERT'        => '稽核證書',
        'CBAM_REPORT'       => 'CBAM 申報',
        'SDS'               => '化學品安全資料表',
        'CMRT'              => '衝突礦產報告範本',
        'DPP_DECLARATION'   => 'DPP 數位產品護照',
        'CPSIA_CERT'        => 'CPSIA 兒童產品安全證明',
        'PROP65_DECLARATION'=> 'Prop 65 警示聲明',
        'MSA_STATEMENT'     => 'Modern Slavery Act 聲明',
        'FORMALDEHYDE_TEST' => '甲醛限量檢測報告',
        'JP_QUALITY_LABEL'  => '品質表示標籤',
    ];

    private const STATUS_PRIORITY = [
        'expired' => 4, 'missing' => 3, 'expiring_soon' => 2, 'valid' => 1,
    ];

    public function __construct(private readonly MarketComplianceChecker $checker) {}

    public function batch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'market'         => ['required', 'string', 'in:EU,US,NA,APAC,GB,JP'],
            'trade_good_ids' => ['required', 'array', 'max:100'],
            'trade_good_ids.*' => ['string', 'uuid'],
        ]);

        $results = $this->checker->checkBatch($validated['trade_good_ids'], $validated['market']);

        return response()->json(['data' => $results]);
    }

    /**
     * 義務缺口明細：此商品在指定市場的每項法規義務，逐一列出負責供應商與文件狀態。
     * 供出口合規看板的「義務缺口明細面板」使用，是「換供應商」操作的資料來源。
     */
    public function gap(Request $request, TradeGood $tradeGood): JsonResponse
    {
        $validated = $request->validate(['market' => ['required', 'string']]);
        $market = strtoupper($validated['market']);

        // 上游供應商來源改用 BOM（ProductBomLine → BomLineSupplier），不用 trade_good_suppliers——
        // 該表在多數商品上未維護（常為空），與 EUDR 徽章先前的資料不一致問題同一根因。
        $tradeGood->load([
            'bomLines.materialGroup',
            'bomLines.bomLineSuppliers.supplier.complianceDocs',
            'bomLines.bomLineSuppliers.supplier.latestRiskAssessment',
        ]);

        // 此商品實際涉及的物料層文件類型（各 BOM 行所屬物料群組要求的文件）
        $materialDocTypes = $tradeGood->bomLines
            ->flatMap(fn ($line) => $line->materialGroup?->required_doc_types ?? [])
            ->unique()->values();

        if ($tradeGood->is_cbam_applicable && $market === 'EU') {
            $materialDocTypes->push('CBAM_REPORT');
        }

        $rules = MarketComplianceRule::active()->forMarket($market)
            ->where(function ($q) use ($materialDocTypes) {
                $q->where('scope', 'product')
                  ->orWhere(fn ($q2) => $q2->where('scope', 'material')
                      ->whereIn('doc_type', $materialDocTypes->isNotEmpty() ? $materialDocTypes->all() : ['__none__']));
            })
            ->get();

        // 供「替代供應商」按鈕判斷用：每個 supplier_id → 其所屬 BOM 行的 material_group_id
        // （同一供應商可能出現在多條 BOM 行，取第一條即可，僅用於粗估是否有其他核准供應商可換）
        $supplierMaterialGroup = [];
        foreach ($tradeGood->bomLines as $line) {
            foreach ($line->bomLineSuppliers as $bls) {
                $supplierMaterialGroup[$bls->supplier_id] ??= $line->material_group_id;
            }
        }
        $groupIds = collect($supplierMaterialGroup)->filter()->unique()->values();
        // 各物料群組（系統全域 AVL）目前有多少不重複核准供應商，用來判斷「換供應商」是否有候選可換
        $supplierCountByGroup = $groupIds->isEmpty() ? collect() : BomLineSupplier::whereIn(
            'bom_line_id', ProductBomLine::whereIn('material_group_id', $groupIds)->pluck('id')
        )->join('product_bom_lines', 'bom_line_suppliers.bom_line_id', '=', 'product_bom_lines.id')
            ->selectRaw('product_bom_lines.material_group_id, COUNT(DISTINCT bom_line_suppliers.supplier_id) as cnt')
            ->groupBy('product_bom_lines.material_group_id')
            ->pluck('cnt', 'material_group_id');

        $obligations = $rules->map(function (MarketComplianceRule $rule) use ($tradeGood, $supplierMaterialGroup, $supplierCountByGroup) {
            // product scope：全部 BOM 上游供應商皆負責；material scope：僅所屬物料群組要求此文件的 BOM 行供應商負責
            $responsibleLines = $rule->scope === 'product'
                ? $tradeGood->bomLines
                : $tradeGood->bomLines->filter(
                    fn ($line) => in_array($rule->doc_type, $line->materialGroup?->required_doc_types ?? [], true)
                );

            $responsibleSuppliers = $responsibleLines
                ->flatMap(fn ($line) => $line->bomLineSuppliers)
                ->filter(fn ($bls) => $bls->supplier !== null)
                ->unique('supplier_id')
                ->map(function ($bls) use ($rule, $supplierMaterialGroup, $supplierCountByGroup) {
                    $supplier = $bls->supplier;
                    $doc = $supplier->complianceDocs->firstWhere('doc_type', $rule->doc_type);
                    $ra  = $supplier->latestRiskAssessment;

                    $groupId = $supplierMaterialGroup[$supplier->id] ?? null;
                    // 群組內供應商數 > 1（扣除自己）才有其他候選可換
                    $hasReplacementCandidates = $groupId && ($supplierCountByGroup[$groupId] ?? 0) > 1;

                    return [
                        'id'                          => $supplier->id,
                        'name'                        => $supplier->name,
                        'doc_status'                  => $doc?->status ?? 'missing',
                        'axis1_score'                 => $ra?->axis1_score,
                        'axis1_level'                 => $this->scoreToLevel($ra?->axis1_score),
                        'has_replacement_candidates'  => $hasReplacementCandidates,
                    ];
                })->values();

            $worstStatus = $responsibleSuppliers->isEmpty()
                ? 'missing'
                : $responsibleSuppliers->reduce(
                    fn ($carry, $s) => (self::STATUS_PRIORITY[$s['doc_status']] ?? 0) > (self::STATUS_PRIORITY[$carry] ?? 0) ? $s['doc_status'] : $carry,
                    'valid'
                );

            return [
                'id'                    => $rule->id,
                'doc_type'              => $rule->doc_type,
                'doc_type_label'        => self::DOC_TYPE_LABELS[$rule->doc_type] ?? $rule->doc_type,
                'regulation_name'       => self::DOC_TYPE_LABELS[$rule->doc_type] ?? $rule->doc_type,
                'is_mandatory'          => $rule->is_mandatory,
                'status'                => $worstStatus,
                'responsible_suppliers' => $responsibleSuppliers,
            ];
        })->values();

        return response()->json(['obligations' => $obligations]);
    }

    /**
     * axis1_score（ESG 揭露風險分數，越高越好）轉五級顯示：
     * ≥80 極低風險 / ≥60 低 / ≥40 中 / ≥20 高 / 其餘 極高。
     */
    private function scoreToLevel(?float $score): ?string
    {
        if ($score === null) return null;
        return match (true) {
            $score >= 80 => 'very_low',
            $score >= 60 => 'low',
            $score >= 40 => 'medium',
            $score >= 20 => 'high',
            default      => 'extreme',
        };
    }
}
