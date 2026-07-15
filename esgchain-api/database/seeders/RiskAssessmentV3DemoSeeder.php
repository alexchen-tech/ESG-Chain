<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RiskAssessmentV3DemoSeeder extends Seeder
{
    public function run(): void
    {
        // 清除舊 RA，重新從 SAQ 推導
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('risk_assessments')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // 取所有已評分 SAQ（含兩輪）
        $saqs = DB::table('saqs as s')
            ->join('saq_projects as p', 'p.id', '=', 's.project_id')
            ->whereNotNull('s.score')
            ->whereNotNull('s.dim_e1')
            ->whereIn('s.status', ['submitted', 'reviewed', 'approved'])
            ->orderBy('s.submitted_at')
            ->get([
                's.id', 's.supplier_id', 's.score',
                's.dim_e1', 's.dim_e2', 's.dim_e3', 's.dim_e4', 's.dim_e5', 's.dim_e6',
                's.submitted_at', 's.updated_at',
                'p.name as project_name', 'p.status as project_status',
            ]);

        $now = now();
        $inserted = 0;
        $latestSaqBySupplier = [];

        foreach ($saqs as $saq) {
            $assessedAt = $saq->submitted_at ?? $saq->updated_at ?? $now;

            DB::table('risk_assessments')->insert([
                'id'                 => Str::uuid()->toString(),
                'supplier_id'        => $saq->supplier_id,
                'assessed_at'        => $assessedAt,
                'assessed_by'        => null,
                'notes'              => "DEMO v3 RA — 從 {$saq->project_name} SAQ 推導",
                'source_saq_id'      => $saq->id,
                'source_type'        => 'saq',
                'source_id'          => $saq->id,
                'dim_e1'             => $saq->dim_e1,
                'dim_e2'             => $saq->dim_e2,
                'dim_e3'             => $saq->dim_e3,
                'dim_e4'             => $saq->dim_e4,
                'dim_e5'             => $saq->dim_e5,
                'dim_e6'             => $saq->dim_e6,
                'assessment_version' => 'v3',
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);

            // 記錄最新 SAQ（active 專案）以同步 risk_score
            if ($saq->project_status === 'active') {
                $latestSaqBySupplier[$saq->supplier_id] = $saq;
            } elseif (!isset($latestSaqBySupplier[$saq->supplier_id])) {
                $latestSaqBySupplier[$saq->supplier_id] = $saq;
            }

            $inserted++;
        }

        // 同步 risk_score（以最新 active 輪為準）
        foreach ($latestSaqBySupplier as $supplierId => $saq) {
            $this->syncRiskScore($supplierId, $saq);
        }

        $this->command->info("Inserted {$inserted} v3 RiskAssessments (2 per supplier).");
    }

    private function syncRiskScore(string $supplierId, object $saq): void
    {
        $weights = ['E1' => 0.25, 'E2' => 0.20, 'E3' => 0.20, 'E4' => 0.15, 'E5' => 0.20, 'E6' => 0.0];
        $dims = [
            'E1' => $saq->dim_e1,
            'E2' => $saq->dim_e2,
            'E3' => $saq->dim_e3,
            'E4' => $saq->dim_e4,
            'E5' => $saq->dim_e5,
        ];

        $weighted = 0.0;
        $totalW = 0.0;
        foreach ($dims as $key => $val) {
            if ($val !== null) {
                $weighted += ($weights[$key] ?? 0) * (float) $val;
                $totalW += $weights[$key] ?? 0;
            }
        }

        if ($totalW > 0) {
            $riskScore = round(1 - ($weighted / $totalW) / 100, 4);
            DB::table('suppliers')->where('id', $supplierId)->update(['risk_score' => $riskScore]);
        }
    }
}
