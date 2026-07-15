<?php

namespace App\Observers;

use App\Models\CAP;
use App\Models\CAPFinding;
use App\Models\RiskAssessment;
use App\Models\Supplier;
use App\Services\Risk\ImpactScoreService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RiskAssessmentObserver
{
    public function created(RiskAssessment $assessment): void
    {
        $this->syncSupplierRiskScore($assessment);
        $this->snapshotImpactScore($assessment);
        $this->checkAndCreateCap($assessment);
    }

    public function updated(RiskAssessment $assessment): void
    {
        $this->syncSupplierRiskScore($assessment);
        $this->snapshotImpactScore($assessment);
    }

    /**
     * 建立/更新評估時重算供應商 impact_score，並快照到本筆評估（point-in-time）。
     * 以 query builder 寫回，繞過 Model 事件避免遞迴。
     */
    private function snapshotImpactScore(RiskAssessment $assessment): void
    {
        $score = app(ImpactScoreService::class)->recomputeForSupplier($assessment->supplier_id);
        if ($score !== null && $assessment->impact_score !== $score) {
            RiskAssessment::where('id', $assessment->id)->update(['impact_score' => $score]);
        }
    }

    private function syncSupplierRiskScore(RiskAssessment $assessment): void
    {
        $dims = ['dim_e1', 'dim_e2', 'dim_e3', 'dim_e4', 'dim_e5', 'dim_e6'];
        $weights = $this->resolvedWeights($assessment);

        $weighted = 0.0;
        $totalWeight = 0.0;
        foreach ($dims as $i => $col) {
            $val = $assessment->{$col};
            $key = 'E' . ($i + 1);
            if ($val !== null && isset($weights[$key])) {
                $weighted += $weights[$key] * $val;
                $totalWeight += $weights[$key];
            }
        }

        if ($totalWeight <= 0) {
            return;
        }

        $riskScore = round(1 - ($weighted / $totalWeight) / 100, 4);

        Supplier::where('id', $assessment->supplier_id)
            ->update(['risk_score' => $riskScore]);
    }

    private function checkAndCreateCap(RiskAssessment $assessment): void
    {
        // legacy/v1 RA 跳過（無 dim_e1–e6 資料）
        $version = $assessment->assessment_version ?? 'v3';
        if (in_array($version, ['legacy', 'v1'])) {
            return;
        }

        $thresholds = $this->capThresholds();
        $findings = [];

        $dimMap = ['E1' => 'dim_e1', 'E2' => 'dim_e2', 'E3' => 'dim_e3',
                   'E4' => 'dim_e4', 'E5' => 'dim_e5', 'E6' => 'dim_e6'];

        foreach ($dimMap as $key => $col) {
            $score = $assessment->{$col};
            if ($score === null) continue; // null 維度跳過（含 E6）
            $threshold = $thresholds[$key] ?? 40;
            if ($score < $threshold) {
                $findings[$key] = $score;
            }
        }

        if (empty($findings)) {
            return;
        }

        $exists = CAP::where('source_type', 'risk_assessment')
            ->where('source_id', $assessment->id)
            ->exists();

        if ($exists) {
            return;
        }

        $supplier = $assessment->supplier;
        $dimLabels = ['E1' => 'ESG整體', 'E2' => '永續採購', 'E3' => '社會責任',
                      'E4' => '地緣政治', 'E5' => '供應鏈安全', 'E6' => '產品合規'];

        $cap = CAP::create([
            'supplier_id' => $assessment->supplier_id,
            'source_type' => 'risk_assessment',
            'source_id'   => $assessment->id,
            'title'       => '風險評估六維警示：' . ($supplier?->name ?? $assessment->supplier_id),
            'description' => implode('、', array_map(fn($k) => $dimLabels[$k] ?? $k, array_keys($findings))) . ' 維度低於閾值，需採取矯正行動',
            'priority'    => 'high',
            'status'      => 'open',
            'due_date'    => Carbon::parse($assessment->assessed_at)->addDays(30)->toDateString(),
            'created_by'  => null,
        ]);

        foreach ($findings as $key => $score) {
            $label = $dimLabels[$key] ?? $key;
            CAPFinding::create([
                'cap_id'   => $cap->id,
                'category' => $key,
                'finding'  => "{$label}（{$key}）評分 {$score}，低於閾值 {$thresholds[$key]}",
                'status'   => 'open',
            ]);
        }
    }

    private function capThresholds(): array
    {
        $setting = DB::table('system_settings')->where('key', 'cap_thresholds')->first();
        if ($setting) {
            return json_decode($setting->value, true) ?? [];
        }
        return ['E1' => 40, 'E2' => 40, 'E3' => 35, 'E4' => 35, 'E5' => 40, 'E6' => 40];
    }

    private function resolvedWeights(RiskAssessment $assessment): array
    {
        $setting = DB::table('system_settings')->where('key', 'dim_weight_defaults')->first();
        $weights = $setting ? (json_decode($setting->value, true) ?? []) : [];

        if (empty($weights)) {
            $w = 1.0 / 6;
            $weights = ['E1' => $w, 'E2' => $w, 'E3' => $w, 'E4' => $w, 'E5' => $w, 'E6' => $w];
        }

        // E6 null 時分攤 E6 weight 到其他五維
        if ($assessment->dim_e6 === null && isset($weights['E6'])) {
            $e6w = $weights['E6'];
            unset($weights['E6']);
            $remaining = array_keys($weights);
            $share = $e6w / count($remaining);
            foreach ($remaining as $k) {
                $weights[$k] += $share;
            }
        }

        return $weights;
    }
}
