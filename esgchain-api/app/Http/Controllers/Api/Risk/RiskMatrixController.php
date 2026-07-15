<?php

namespace App\Http\Controllers\Api\Risk;

use App\Http\Controllers\Controller;
use App\Models\CAP;
use App\Models\RiskAssessment;
use App\Models\SAQ;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiskMatrixController extends Controller
{
    private const VALID_DIMENSIONS = ['E1', 'E2', 'E3', 'E4', 'E5', 'COMPOSITE'];

    // 六維欄位對應表；COMPOSITE 特殊處理（LEAST）
    private const DIM_SCORE_FIELD = [
        'e1'        => 'dim_e1',
        'e2'        => 'dim_e2',
        'e3'        => 'dim_e3',
        'e4'        => 'dim_e4',
        'e5'        => 'dim_e5',
        'composite' => null,
    ];

    public function matrix(Request $request): JsonResponse
    {
        $request->validate([
            'dimension' => ['required', 'string', 'in:E1,E2,E3,E4,E5,COMPOSITE'],
        ]);

        $dim = strtolower($request->dimension);
        $matrix = $this->buildMatrix($dim);
        $summary = $this->buildSummary($matrix);

        return response()->json([
            'success' => true,
            'data' => [
                'dimension' => strtoupper($dim),
                'matrix'    => $matrix,
                'summary'   => $summary,
            ],
            'message' => '',
        ]);
    }

    public function matrixSuppliers(Request $request): JsonResponse
    {
        $request->validate([
            'dimension'   => ['required', 'string', 'in:E1,E2,E3,E4,E5,COMPOSITE'],
            'probability' => ['required', 'integer', 'min:1', 'max:5'],
            'impact'      => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $dim  = strtolower($request->dimension);
        $prob = $request->integer('probability');
        $imp  = $request->integer('impact');

        $isComposite = ($dim === 'composite');
        $scoreField  = self::DIM_SCORE_FIELD[$dim] ?? 'dim_e1';

        // 每個供應商只取最新一筆，與 buildMatrix 邏輯一致
        $latestSub = RiskAssessment::selectRaw('supplier_id, MAX(assessed_at) as max_at')
            ->groupBy('supplier_id');

        $selectExpr = $isComposite
            ? DB::raw('LEAST(dim_e1, dim_e2, dim_e3, dim_e4, dim_e5) as dim_score')
            : DB::raw("{$scoreField} as dim_score");

        $allRows = RiskAssessment::joinSub($latestSub, 'latest', function ($join) {
            $join->on('risk_assessments.supplier_id', '=', 'latest.supplier_id')
                 ->on('risk_assessments.assessed_at', '=', 'latest.max_at');
        })
            ->join('suppliers', 'risk_assessments.supplier_id', '=', 'suppliers.id')
            ->select('risk_assessments.supplier_id', $selectExpr, 'suppliers.impact_score')
            ->get();

        $supplierIds = $allRows->filter(function ($row) use ($prob, $imp) {
            return $this->scoreToProb($row->dim_score) === $prob
                && $this->resolveImpact($row->impact_score) === $imp;
        })->pluck('supplier_id');

        $paginated = Supplier::whereIn('id', $supplierIds)
            ->paginate($request->integer('per_page', 20));

        $supplierList = collect($paginated->items());

        // 批次載入最新 RiskAssessment（全維度）
        $latestRa = RiskAssessment::joinSub($latestSub, 'latest2', function ($join) {
            $join->on('risk_assessments.supplier_id', '=', 'latest2.supplier_id')
                 ->on('risk_assessments.assessed_at', '=', 'latest2.max_at');
        })
            ->whereIn('risk_assessments.supplier_id', $supplierList->pluck('id'))
            ->get(['risk_assessments.*'])
            ->keyBy('supplier_id');

        // 批次載入最新 SAQ（score + grade）
        $latestSaqSub = SAQ::selectRaw('supplier_id, MAX(created_at) as max_at')
            ->whereNotNull('score')
            ->groupBy('supplier_id');

        $latestSaq = SAQ::joinSub($latestSaqSub, 'lsaq', function ($join) {
            $join->on('saqs.supplier_id', '=', 'lsaq.supplier_id')
                 ->on('saqs.created_at', '=', 'lsaq.max_at');
        })
            ->whereIn('saqs.supplier_id', $supplierList->pluck('id'))
            ->get(['saqs.supplier_id', 'saqs.score', 'saqs.grade', 'saqs.status'])
            ->keyBy('supplier_id');

        // 批次載入 Open CAP 數量
        $openCapCounts = CAP::whereIn('supplier_id', $supplierList->pluck('id'))
            ->where('status', 'open')
            ->selectRaw('supplier_id, COUNT(*) as cnt')
            ->groupBy('supplier_id')
            ->pluck('cnt', 'supplier_id');

        $data = $supplierList->map(function ($supplier) use ($latestRa, $latestSaq, $openCapCounts, $isComposite, $scoreField) {
            $ra  = $latestRa->get($supplier->id);
            $saq = $latestSaq->get($supplier->id);

            $worstDimKey = $isComposite
                ? ($ra ? $this->getWorstDimKey($ra) : null)
                : $scoreField;

            return [
                'id'           => $supplier->id,
                'name'         => $supplier->name,
                'code'         => $supplier->code,
                'country_code' => $supplier->country_code,
                'tier'         => $supplier->tier,
                'impact_score' => $supplier->impact_score,
                'status'       => $supplier->status,
                'saq_score'    => $saq?->score,
                'saq_grade'    => $saq?->grade,
                'saq_status'   => $saq?->status,
                'open_cap_count' => (int) ($openCapCounts->get($supplier->id, 0)),
                'worst_dim_key'  => $worstDimKey,
                'risk_assessment' => $ra ? [
                    'id'              => $ra->id,
                    'assessed_at'     => $ra->assessed_at,
                    'notes'           => $ra->notes,
                    'dim_e1'          => $ra->dim_e1,
                    'dim_e2'          => $ra->dim_e2,
                    'dim_e3'          => $ra->dim_e3,
                    'dim_e4'          => $ra->dim_e4,
                    'dim_e5'          => $ra->dim_e5,
                    'dim_e6'          => $ra->dim_e6,
                    'assessment_version' => $ra->assessment_version,
                    'source_type'     => $ra->source_type,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
            'message' => '',
        ]);
    }

    private function getWorstDimKey(RiskAssessment $ra): string
    {
        $dims = [
            'dim_e1' => $ra->dim_e1,
            'dim_e2' => $ra->dim_e2,
            'dim_e3' => $ra->dim_e3,
            'dim_e4' => $ra->dim_e4,
            'dim_e5' => $ra->dim_e5,
        ];
        $dims = array_filter($dims, fn($v) => $v !== null);
        if (empty($dims)) return 'dim_e1';
        $minVal = min($dims);
        return (string) array_key_first(array_filter($dims, fn($v) => $v === $minVal));
    }

    private function cellScoreToLevel(int $score): string
    {
        if ($score >= 20) return 'extreme';
        if ($score >= 15) return 'high';
        if ($score >= 10) return 'medium';
        if ($score >= 5)  return 'low';
        return 'very_low';
    }

    // Probability = max(1, min(5, ceil((100 - score) / 20)))
    private function scoreToProb(?float $score): int
    {
        if ($score === null) return 3; // 無資料預設中等
        return max(1, min(5, (int) ceil((100 - $score) / 20)));
    }

    // Impact = 供應商四因子加權 impact_score（由 esgchain-ai 計算並存於 suppliers）；
    // 尚未計算（null）時以中性值 3 落點，與 AI 缺值規則一致。
    private function resolveImpact(?int $impactScore): int
    {
        if ($impactScore === null) return 3;
        return max(1, min(5, $impactScore));
    }

    private function buildMatrix(string $dim): array
    {
        $isComposite = ($dim === 'composite');
        $scoreField  = self::DIM_SCORE_FIELD[$dim] ?? 'dim_e1';

        $selectExpr = $isComposite
            ? DB::raw('LEAST(dim_e1, dim_e2, dim_e3, dim_e4, dim_e5) as dim_score')
            : DB::raw("{$scoreField} as dim_score");

        // 每個供應商只取最新一筆評估
        $latestSub = RiskAssessment::selectRaw('supplier_id, MAX(assessed_at) as max_at')
            ->groupBy('supplier_id');

        $latestBySupplier = RiskAssessment::joinSub($latestSub, 'latest', function ($join) {
            $join->on('risk_assessments.supplier_id', '=', 'latest.supplier_id')
                 ->on('risk_assessments.assessed_at', '=', 'latest.max_at');
        })
            ->join('suppliers', 'risk_assessments.supplier_id', '=', 'suppliers.id')
            ->select(
                'risk_assessments.supplier_id',
                $selectExpr,
                'suppliers.impact_score'
            )
            ->get();

        // 計算每個供應商的 P 和 I
        $supplierProbImpact = $latestBySupplier->map(fn($row) => [
            'supplier_id' => $row->supplier_id,
            'probability' => $this->scoreToProb($row->dim_score),
            'impact'      => $this->resolveImpact($row->impact_score),
        ]);

        $allSupplierIds = $supplierProbImpact->pluck('supplier_id');

        // 批次取得有 Open CAP 的供應商集合
        $openCapSupplierIds = CAP::whereIn('supplier_id', $allSupplierIds)
            ->where('status', 'open')
            ->pluck('supplier_id')
            ->unique()
            ->flip();

        // 批次取得最新 SAQ grade
        $latestSaqSub = SAQ::selectRaw('supplier_id, MAX(created_at) as max_at')
            ->whereNotNull('score')
            ->groupBy('supplier_id');

        $saqGrades = SAQ::joinSub($latestSaqSub, 'lsaq2', function ($join) {
            $join->on('saqs.supplier_id', '=', 'lsaq2.supplier_id')
                 ->on('saqs.created_at', '=', 'lsaq2.max_at');
        })
            ->whereIn('saqs.supplier_id', $allSupplierIds)
            ->pluck('saqs.grade', 'saqs.supplier_id');

        $cells = [];
        for ($p = 1; $p <= 5; $p++) {
            for ($i = 1; $i <= 5; $i++) {
                $cellSupplierIds = $supplierProbImpact
                    ->where('probability', $p)
                    ->where('impact', $i)
                    ->pluck('supplier_id');

                $count = $cellSupplierIds->count();
                $openCapCount = $cellSupplierIds->filter(fn($id) => $openCapSupplierIds->has($id))->count();

                $gradeDist = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0];
                $noSaqCount = 0;
                foreach ($cellSupplierIds as $sid) {
                    $grade = $saqGrades->get($sid);
                    if ($grade && isset($gradeDist[$grade])) {
                        $gradeDist[$grade]++;
                    } else {
                        $noSaqCount++;
                    }
                }

                $score = $p * $i;
                $cells[] = [
                    'probability'    => $p,
                    'impact'         => $i,
                    'cell_score'     => $score,
                    'risk_level'     => $this->cellScoreToLevel($score),
                    'supplier_count' => $count,
                    'open_cap_count' => $openCapCount,
                    'saq_grade_dist' => $gradeDist,
                    'no_saq_count'   => $noSaqCount,
                ];
            }
        }
        return $cells;
    }

    private function buildSummary(array $matrix): array
    {
        $counts = ['extreme' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'very_low' => 0];
        $total  = 0;

        foreach ($matrix as $cell) {
            $counts[$cell['risk_level']] += $cell['supplier_count'];
            $total += $cell['supplier_count'];
        }

        return array_merge($counts, [
            'extreme_count'  => $counts['extreme'],
            'high_count'     => $counts['high'],
            'medium_count'   => $counts['medium'],
            'low_count'      => $counts['low'],
            'very_low_count' => $counts['very_low'],
            'total'          => $total,
        ]);
    }
}
