<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DEMO 資料補齊：確保每一家啟用中供應商都有 2024、2025 年度已評分 SAQ，
 * 讓「風險歷史」有足夠跨年度資料點可呈現趨勢（風險評估僅由已評分 SAQ 自動推導，
 * 見 RiskAssessmentController::store() 的問卷驅動立場）。
 *
 * 2024：全新建立一個 saq_projects（現有資料完全沒有 2024 年度專案），
 * 39 家供應商全部補建已評分 SAQ。
 * 2025：沿用既有「2025 H2 全供應商六維度複評」專案，僅補齊目前缺漏的 4 家
 * （皆為近期新增的 Tier 4 原料供應商）。
 */
class SaqYearlyBackfillDemoSeeder extends Seeder
{
    public function run(): void
    {
        $templateId = DB::table('saq_templates')->value('id');
        $seriesId = DB::table('saq_projects')->value('series_id');

        if (!$templateId) {
            $this->command?->warn('找不到 saq_templates，略過 SAQ 補齊');
            return;
        }

        $project2024Id = $this->ensureProject2024($templateId, $seriesId);
        $project2025Id = DB::table('saq_projects')
            ->where('name', '2025 H2 全供應商六維度複評')
            ->value('id');

        $suppliers = DB::table('suppliers')->whereNull('deleted_at')->get(['id', 'name']);

        $created2024 = 0;
        $created2025 = 0;

        foreach ($suppliers as $supplier) {
            if (!$this->hasScoredSaq($supplier->id, 2024)) {
                $this->insertScoredSaq($project2024Id, $templateId, $supplier->id, 2024);
                $created2024++;
            }

            if ($project2025Id && !$this->hasScoredSaq($supplier->id, 2025)) {
                $this->insertScoredSaq($project2025Id, $templateId, $supplier->id, 2025);
                $created2025++;
            }
        }

        $this->command?->info("2024 年度 SAQ：新增 {$created2024} 筆");
        $this->command?->info("2025 年度 SAQ：新增 {$created2025} 筆");
    }

    private function hasScoredSaq(string $supplierId, int $year): bool
    {
        return DB::table('saqs')
            ->where('supplier_id', $supplierId)
            ->whereYear('submitted_at', $year)
            ->whereNotNull('score')
            ->exists();
    }

    private function ensureProject2024(string $templateId, ?string $seriesId): string
    {
        $existing = DB::table('saq_projects')->where('name', '2024 H2 全供應商六維度複評')->first();
        if ($existing) {
            return $existing->id;
        }

        $id = (string) Str::orderedUuid();
        $now = now();

        DB::table('saq_projects')->insert([
            'id'               => $id,
            'name'             => '2024 H2 全供應商六維度複評',
            'template_id'      => $templateId,
            'status'           => 'closed',
            'closed_at'        => Carbon::create(2024, 12, 31, 18, 0, 0),
            'start_date'       => '2024-07-01',
            'due_date'         => '2024-12-31',
            'description'      => 'DEMO 資料：補齊 2024 年度歷史 SAQ，供風險歷史趨勢呈現',
            'series_id'        => $seriesId,
            'is_comparable'    => 1,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        return $id;
    }

    private function insertScoredSaq(string $projectId, string $templateId, string $supplierId, int $year): void
    {
        $base = random_int(35, 75);
        $dims = [];
        foreach (['dim_e1', 'dim_e2', 'dim_e3', 'dim_e4', 'dim_e5'] as $key) {
            $dims[$key] = min(100, max(0, $base + random_int(-12, 12)));
        }

        $score = round(array_sum($dims) / count($dims), 2);
        $grade = match (true) {
            $score >= 80 => 'A',
            $score >= 65 => 'B',
            $score >= 50 => 'C',
            default      => 'D',
        };

        $month = $year === 2024 ? random_int(9, 11) : random_int(7, 11);
        $submittedAt = Carbon::create($year, $month, random_int(1, 25), 9, 0, 0);

        DB::table('saqs')->insert([
            'id'           => (string) Str::orderedUuid(),
            'project_id'   => $projectId,
            'supplier_id'  => $supplierId,
            'template_id'  => $templateId,
            'status'       => 'submitted',
            'score'        => $score,
            'score_e'      => $score,
            'score_s'      => $score,
            'score_g'      => $score,
            'dim_e1'       => $dims['dim_e1'],
            'dim_e2'       => $dims['dim_e2'],
            'dim_e3'       => $dims['dim_e3'],
            'dim_e4'       => $dims['dim_e4'],
            'dim_e5'       => $dims['dim_e5'],
            'grade'        => $grade,
            'final_score'  => $score,
            'final_grade'  => $grade,
            'sent_at'      => $submittedAt->copy()->subDays(30),
            'submitted_at' => $submittedAt,
            'created_at'   => $submittedAt,
            'updated_at'   => $submittedAt,
        ]);
    }
}
