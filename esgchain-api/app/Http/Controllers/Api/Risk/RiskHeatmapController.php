<?php

namespace App\Http\Controllers\Api\Risk;

use App\Http\Controllers\Controller;
use App\Models\CAP;
use App\Models\RiskAssessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiskHeatmapController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $beforeDays = $request->query('before_days');

        if ($beforeDays !== null) {
            $beforeDays = (int) $beforeDays;
            if ($beforeDays < 1 || $beforeDays > 365) {
                return response()->json(['message' => 'before_days 必須介於 1–365'], 422);
            }
        }

        $thresholds = $this->capThresholds();

        // 每家供應商取最新 RA（before_days 有值時限制日期上限）
        $cutoff = $beforeDays ? now()->subDays($beforeDays)->toDateTimeString() : null;

        $latestIds = DB::table('risk_assessments as r1')
            ->when($cutoff, fn($q) => $q->where('r1.assessed_at', '<=', $cutoff))
            ->whereRaw('r1.assessed_at = (SELECT MAX(r2.assessed_at) FROM risk_assessments r2 WHERE r2.supplier_id = r1.supplier_id' . ($cutoff ? " AND r2.assessed_at <= '$cutoff'" : '') . ')')
            ->pluck('r1.id');

        // withTrashed on supplier to include soft-deleted suppliers (heatmap is risk audit, not supplier mgmt)
        $ras = RiskAssessment::with(['supplier' => fn($q) => $q->withTrashed()->select('id', 'name', 'code', 'country_code', 'risk_score')])
            ->whereIn('id', $latestIds)
            ->orderBy('assessed_at', 'desc')
            ->get();

        $openCapCounts = CAP::where('source_type', 'risk_assessment')
            ->where('status', 'open')
            ->whereIn('source_id', $ras->pluck('id'))
            ->selectRaw('source_id, COUNT(*) as cnt')
            ->groupBy('source_id')
            ->pluck('cnt', 'source_id');

        $data = $ras->map(function ($ra) use ($thresholds, $openCapCounts) {
            $isCritical = false;
            $dimMap = ['E1' => 'dim_e1', 'E2' => 'dim_e2', 'E3' => 'dim_e3',
                       'E4' => 'dim_e4', 'E5' => 'dim_e5', 'E6' => 'dim_e6'];

            foreach ($dimMap as $key => $col) {
                $score = $ra->{$col};
                if ($score !== null && $score < ($thresholds[$key] ?? 40)) {
                    $isCritical = true;
                    break;
                }
            }

            return [
                'supplier_id'   => $ra->supplier_id,
                'supplier_name' => $ra->supplier?->name,
                'supplier_code' => $ra->supplier?->code,
                'country_code'  => $ra->supplier?->country_code,
                'assessed_at'   => $ra->assessed_at?->toIso8601String(),
                'source_type'   => $ra->source_type ?? 'saq',
                'dim_e1'        => $ra->dim_e1,
                'dim_e2'        => $ra->dim_e2,
                'dim_e3'        => $ra->dim_e3,
                'dim_e4'        => $ra->dim_e4,
                'dim_e5'        => $ra->dim_e5,
                'dim_e6'        => $ra->dim_e6,
                'open_cap_count' => $openCapCounts[$ra->id] ?? 0,
                'risk_score'    => $ra->supplier?->risk_score,
                'is_critical'   => $isCritical,
            ];
        });

        $critical = $data->filter(fn($r) => $r['is_critical'])->count();

        return response()->json([
            'data'       => $data->values(),
            'thresholds' => $thresholds,
            'summary'    => [
                'total'            => $data->count(),
                'any_dim_critical' => $critical,
            ],
        ]);
    }

    private function capThresholds(): array
    {
        $setting = DB::table('system_settings')->where('key', 'cap_thresholds')->first();
        if ($setting) {
            return json_decode($setting->value, true) ?? [];
        }
        return ['E1' => 40, 'E2' => 40, 'E3' => 35, 'E4' => 35, 'E5' => 40, 'E6' => 40];
    }
}
