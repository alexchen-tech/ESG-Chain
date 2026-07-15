<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        $tradeGood       = TradeGood::with('suppliers.supplier')->findOrFail($tradeGoodId);
        $replaceSupplier = Supplier::findOrFail($replaceSupplierId);

        // 碳排占比
        $pathRiskCache  = TradeGoodPathRisk::where('trade_good_id', $tradeGoodId)->where('market', $market)->first();
        $contributors   = $pathRiskCache?->contributors ?? [];
        $replaceContrib = collect($contributors)->firstWhere('supplier_id', $replaceSupplierId);
        $carbonShare    = $replaceContrib['carbon_share'] ?? 0.0;

        // 被替換廠的最新六維分數與 total_score
        $replaceRisk  = $this->latestRiskWithDims($replaceSupplierId);
        $replaceAxis1 = $replaceRisk?->axis1_score ?? 80.0;

        $bomSupplierIds = $tradeGood->suppliers->pluck('supplier_id')->toArray();

        // 查找候選供應商：同 HS Code、異來源國，且有六維資料
        $rawCandidates = Supplier::where('country_code', '!=', $replaceSupplier->country_code)
            ->where('hs_code', $replaceSupplier->hs_code)
            ->whereHas('riskAssessments', fn ($q) => $q->whereNotNull('dim_e1'))
            ->with(['riskAssessments' => fn ($q) => $q->whereNotNull('dim_e1')->orderByDesc('assessed_at')])
            ->limit(50)
            ->get();

        // 計算候選分數，套用硬性過濾
        $fallback   = false;
        $candidates = $this->scoreCandidates($rawCandidates, $fallback);

        if (empty($candidates)) {
            return response()->json([
                'candidates' => [],
                'message'    => '系統內無符合條件的替換候選供應商',
            ]);
        }

        $aiUrl = config('services.ai.url', env('AI_SERVICE_URL', 'http://esgchain-ai:8000'));

        $resp = Http::timeout(30)->post("{$aiUrl}/ai/v1/path-risk/replacement-candidates", [
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
     * 評分：total_score × 0.5 + six_dim_weighted × 0.5
     * 硬性過濾：min(dim_e1..e5) ≥ REPLACEMENT_MIN_SCORE
     * 若過濾後候選池為空，退化為純 total_score 排序並設 fallback=true。
     *
     * @return array<int, array<string, mixed>>
     */
    private function scoreCandidates(\Illuminate\Support\Collection $rawCandidates, bool &$fallback): array
    {
        $minScore = SixDimRiskThresholds::REPLACEMENT_MIN_SCORE;

        $scored = $rawCandidates->map(function (Supplier $s) {
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
            $sixDimScore    = $weightTotal > 0 ? $weightedSum / $weightTotal : $totalScore;
            $candidateScore = (float) $totalScore * 0.5 + $sixDimScore * 0.5;

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
                'co2_kg'         => null,
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

    private function latestRiskWithDims(string $supplierId): ?RiskAssessment
    {
        return RiskAssessment::where('supplier_id', $supplierId)
            ->orderByDesc('assessed_at')
            ->first();
    }
}
