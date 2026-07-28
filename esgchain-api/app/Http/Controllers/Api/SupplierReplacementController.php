<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BomLineSupplier;
use App\Models\MaterialItemEmission;
use App\Models\ProductBomLine;
use App\Models\RiskAssessment;
use App\Models\Supplier;
use App\Models\TradeGood;
use App\Models\TradeGoodPathRisk;
use App\Services\Risk\SixDimRiskThresholds;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SupplierReplacementController extends Controller
{
    /** 六維預設權重（與 SixDimRiskThresholds 解耦，此處用於候選評分） */
    private const DIM_WEIGHTS = [
        'dim_e1' => 0.25,
        'dim_e2' => 0.15,
        'dim_e3' => 0.20,
        'dim_e4' => 0.15,
        'dim_e5' => 0.10,
    ];

    public function candidates(Request $request): JsonResponse
    {
        $request->validate([
            'trade_good_id'       => ['required', 'uuid'],
            'market'              => ['required', 'string', 'in:EU,US,APAC,GB,JP'],
            'replace_supplier_id' => ['required', 'uuid'],
        ]);

        $tradeGoodId       = $request->trade_good_id;
        $market            = $request->market;
        $replaceSupplierId = $request->replace_supplier_id;

        $tradeGood       = TradeGood::with('bomLines.bomLineSuppliers')->findOrFail($tradeGoodId);
        $replaceSupplier = Supplier::findOrFail($replaceSupplierId);

        // 碳排占比
        $pathRiskCache  = TradeGoodPathRisk::where('trade_good_id', $tradeGoodId)->where('market', $market)->first();
        $contributors   = $pathRiskCache?->contributors ?? [];
        $replaceContrib = collect($contributors)->firstWhere('supplier_id', $replaceSupplierId);
        $carbonShare    = $replaceContrib['carbon_share'] ?? 0.0;

        // 被替換廠的最新六維分數與 total_score
        $replaceRisk  = $this->latestRiskWithDims($replaceSupplierId);
        $replaceAxis1 = $replaceRisk?->axis1_score ?? 80.0;

        // 上游供應商來源改用 BOM（ProductBomLine → BomLineSupplier），不用 trade_good_suppliers——
        // 該表在多數商品上未維護，與義務缺口面板、EUDR 徽章先前的資料不一致問題同一根因。
        $bomSupplierIds = $tradeGood->bomLines
            ->flatMap(fn ($line) => $line->bomLineSuppliers)
            ->pluck('supplier_id')->unique()->values()->toArray();

        // 被替換供應商在此商品供應鏈中負責的物料群組（Supplier 無 hs_code 欄位，
        // 改以「同物料群組的核准供應商」作候選來源，貼合實際 AVL/BOM 資料模型）
        $materialGroupId = $tradeGood->bomLines
            ->first(fn ($line) => $line->bomLineSuppliers->contains('supplier_id', $replaceSupplierId))
            ?->material_group_id;

        $candidateSupplierIds = $materialGroupId
            ? BomLineSupplier::whereIn('bom_line_id',
                ProductBomLine::where('material_group_id', $materialGroupId)->pluck('id'))
                ->pluck('supplier_id')->unique()
            : collect();

        // 查找候選供應商：同物料群組的核准供應商、異來源國、排除被替換者本身，且有六維資料
        $rawCandidates = Supplier::whereIn('id', $candidateSupplierIds)
            ->where('id', '!=', $replaceSupplierId)
            ->where('country_code', '!=', $replaceSupplier->country_code)
            ->whereHas('riskAssessments', fn ($q) => $q->whereNotNull('dim_e1'))
            ->with(['riskAssessments' => fn ($q) => $q->whereNotNull('dim_e1')->orderByDesc('assessed_at')])
            ->limit(50)
            ->get();

        // 實測物料碳排：取該群組內、此商品 BOM 實際使用的物料，
        // 供候選評分納入實測碳足跡（而非僅 SAQ 氣候治理分數）
        $carbonBySupplier = $materialGroupId
            ? $this->materialCarbonByGroup($tradeGood, $materialGroupId)
            : [];

        // 計算候選分數，套用硬性過濾
        $fallback   = false;
        $candidates = $this->scoreCandidates($rawCandidates, $fallback, $carbonBySupplier);

        if (empty($candidates)) {
            return response()->json([
                'candidates' => [],
                'message'    => '系統內無符合條件的替換候選供應商',
            ]);
        }

        $aiUrl = config('services.ai.url', env('AI_SERVICE_URL', 'http://esgchain-ai:8000'));

        // AI 端點需 JWT（get_current_user 只驗不發），轉發本次請求的使用者 token
        $resp = Http::withToken($request->bearerToken())->timeout(30)->post("{$aiUrl}/ai/v1/path-risk/replacement-candidates", [
            'trade_good_id'                 => $tradeGoodId,
            'market'                        => $market,
            'replace_supplier_id'           => $replaceSupplierId,
            'current_path_risk_score'       => $pathRiskCache?->path_risk_score ?? 0.0,
            'current_chain_risk'            => $pathRiskCache?->chain_risk ?? 0.0,
            'replace_supplier_carbon_share' => $carbonShare,
            'replace_supplier_axis1_score'  => $replaceAxis1,
            'candidates'                    => $candidates,
            'bom_supplier_ids'              => $bomSupplierIds,
            'fallback'                      => $fallback,
        ]);

        if ($resp->failed()) {
            return response()->json(['candidates' => [], 'message' => 'AI 推薦服務暫時無法使用'], 503);
        }

        return response()->json($resp->json());
    }

    /**
     * 計算候選廠分數並套用硬性過濾。
     *
     * 評分：
     *   有實測碳排資料時 → total_score × 0.35 + six_dim_weighted × 0.35 + carbon_score × 0.30
     *   無實測碳排資料時 → total_score × 0.5 + six_dim_weighted × 0.5（原公式，優雅退化）
     * carbon_score：候選在「被替換供應商所屬物料群組」的實測碳排值，於候選池中的相對百分位
     *   （碳排越低分數越高，0–100），非 SAQ dim_e2 氣候治理分數的替代，是額外訊號。
     * 硬性過濾：min(dim_e1..e5) ≥ REPLACEMENT_MIN_SCORE
     * 若過濾後候選池為空，退化為純 total_score 排序並設 fallback=true。
     *
     * @param  array<string, float>  $carbonBySupplier  supplier_id => 該供應商在相關物料群組的平均實測碳排值
     * @return array<int, array<string, mixed>>
     */
    private function scoreCandidates(\Illuminate\Support\Collection $rawCandidates, bool &$fallback, array $carbonBySupplier = []): array
    {
        $minScore = SixDimRiskThresholds::REPLACEMENT_MIN_SCORE;

        // 碳排百分位轉換：越低碳排分數越高（0–100）
        $carbonValues = array_values($carbonBySupplier);
        $carbonMin    = $carbonValues ? min($carbonValues) : null;
        $carbonMax    = $carbonValues ? max($carbonValues) : null;
        $toCarbonScore = function (?float $value) use ($carbonMin, $carbonMax): ?float {
            if ($value === null || $carbonMin === null || $carbonMax === null) return null;
            if ($carbonMax === $carbonMin) return 100.0; // 全部候選碳排相同，給滿分不影響排序
            return round((1 - ($value - $carbonMin) / ($carbonMax - $carbonMin)) * 100, 2);
        };

        $scored = $rawCandidates->map(function (Supplier $s) use ($carbonBySupplier, $toCarbonScore) {
            $ra = $s->riskAssessments->first();
            if (!$ra) return null;

            $dims = [
                'dim_e1' => $ra->dim_e1 !== null ? (float) $ra->dim_e1 : null,
                'dim_e2' => $ra->dim_e2 !== null ? (float) $ra->dim_e2 : null,
                'dim_e3' => $ra->dim_e3 !== null ? (float) $ra->dim_e3 : null,
                'dim_e4' => $ra->dim_e4 !== null ? (float) $ra->dim_e4 : null,
                'dim_e5' => $ra->dim_e5 !== null ? (float) $ra->dim_e5 : null,
            ];

            $activeDims   = array_filter($dims, fn ($v) => $v !== null);
            $minDimScore  = empty($activeDims) ? null : min($activeDims);
            $totalScore   = $ra->saq_score ?? $ra->axis1_score ?? 50.0;

            // 六維加權分
            $weightedSum  = 0.0;
            $weightTotal  = 0.0;
            foreach (self::DIM_WEIGHTS as $field => $w) {
                if ($dims[$field] !== null) {
                    $weightedSum += $dims[$field] * $w;
                    $weightTotal += $w;
                }
            }
            $sixDimScore = $weightTotal > 0 ? $weightedSum / $weightTotal : $totalScore;

            $co2Kg      = $carbonBySupplier[$s->id] ?? null;
            $carbonScore = $toCarbonScore($co2Kg);

            $candidateScore = $carbonScore !== null
                ? (float) $totalScore * 0.35 + $sixDimScore * 0.35 + $carbonScore * 0.30
                : (float) $totalScore * 0.5 + $sixDimScore * 0.5;

            return [
                'supplier_id'    => $s->id,
                'name'           => $s->name,
                'country_code'   => $s->country_code,
                'axis1_score'    => $ra->axis1_score,
                'total_score'    => $totalScore,
                'candidate_score'=> round($candidateScore, 2),
                'min_dim_score'  => $minDimScore,
                'dim_e1'         => $dims['dim_e1'],
                'dim_e2'         => $dims['dim_e2'],
                'dim_e3'         => $dims['dim_e3'],
                'dim_e4'         => $dims['dim_e4'],
                'dim_e5'         => $dims['dim_e5'],
                'co2_kg'         => $co2Kg,
                'carbon_score'   => $carbonScore,
            ];
        })->filter()->values();

        // 硬性過濾
        $filtered = $scored->filter(fn ($c) => $c['min_dim_score'] === null || $c['min_dim_score'] >= $minScore);

        if ($filtered->isEmpty()) {
            // 退化：全部候選池，純 total_score 排序
            $fallback = true;
            return $scored->sortByDesc('total_score')->values()->toArray();
        }

        return $filtered->sortByDesc('candidate_score')->values()->toArray();
    }

    /**
     * 取指定物料群組內、此商品 BOM 實際使用的物料，
     * 回傳「供應商 → 平均實測碳排值」（含被替換供應商自身，供比較基準）。
     * 找不到對應物料或無任何實測資料時回傳空陣列（呼叫端優雅退化為純 ESG 評分）。
     *
     * @return array<string, float>
     */
    private function materialCarbonByGroup(TradeGood $tradeGood, string $materialGroupId): array
    {
        $materialIds = ProductBomLine::where('sales_product_id', $tradeGood->id)
            ->where('material_group_id', $materialGroupId)
            ->pluck('material_item_id')
            ->filter()
            ->unique();

        if ($materialIds->isEmpty()) return [];

        return MaterialItemEmission::whereIn('material_item_id', $materialIds)
            ->whereNotNull('supplier_id')
            ->where('is_flagged', false)
            ->get()
            ->groupBy('supplier_id')
            ->map(fn ($group) => round((float) $group->avg('emissions_value'), 4))
            ->toArray();
    }

    private function latestRiskWithDims(string $supplierId): ?RiskAssessment
    {
        return RiskAssessment::where('supplier_id', $supplierId)
            ->orderByDesc('assessed_at')
            ->first();
    }
}
