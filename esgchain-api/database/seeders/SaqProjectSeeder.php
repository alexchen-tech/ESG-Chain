<?php

namespace Database\Seeders;

use App\Models\AssessmentSeries;
use App\Models\SAQTemplate;
use App\Models\SaqProject;
use App\Services\SAQ\ProjectQuestionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SaqProjectSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = DB::table('users')->where('email', 'admin@esgchain.com')->value('id');

        // 取各框架的 published 範本（Geo-Risk 取第一個：ISO28000）
        $templates = SAQTemplate::where('status', 'published')
            ->get()
            ->groupBy('scoring_framework')
            ->map(fn($group) => $group->first());

        $esgTemplate     = $templates->get('ESG');
        $iso20400Template = $templates->get('ISO20400');
        $iso26Template   = $templates->get('ISO26000');
        $geoRiskTemplate = $templates->get('Geo-Risk');

        if (!$esgTemplate || !$iso20400Template || !$iso26Template || !$geoRiskTemplate) {
            $this->command->warn('Missing templates, skipping SaqProjectSeeder.');
            return;
        }

        $snapshotService = app(ProjectQuestionService::class);

        // ── 建立評核系列（每個框架一個系列）──
        $seriesData = [
            [
                'name'        => '年度 ESG 全維度評核系列',
                'description' => '涵蓋 E/S/G 三大支柱的年度供應商評核計畫',
                'template'    => $esgTemplate,
            ],
            [
                'name'        => 'ISO 20400 永續採購稽核系列',
                'description' => '依 ISO 20400 框架進行的採購稽核系列',
                'template'    => $iso20400Template,
            ],
            [
                'name'        => 'ISO 26000 社會責任評核系列',
                'description' => '依 ISO 26000 七大核心主題進行的社會責任評核',
                'template'    => $iso26Template,
            ],
            [
                'name'        => '供應鏈安全與地緣風險評核系列',
                'description' => '評估供應鏈安全管理與地緣政治暴露程度',
                'template'    => $geoRiskTemplate,
            ],
        ];

        $createdSeries = [];
        foreach ($seriesData as $s) {
            $series = AssessmentSeries::firstOrCreate(
                ['name' => $s['name']],
                [
                    'description'                  => $s['description'],
                    'template_id'                  => $s['template']->id,
                    'template_version_at_creation' => $s['template']->version,
                    'status'                       => 'active',
                    'created_by_id'                => $adminId,
                ]
            );
            $createdSeries[$s['name']] = $series;
        }

        // ── 建立各系列的問卷專案 ──
        $projectsData = [
            // ESG 系列
            [
                'series' => '年度 ESG 全維度評核系列',
                'name'   => '2024 Q4 年度 ESG 問卷（已結案）',
                'status' => 'closed',
                'due_date' => '2024-12-31',
                'closed_at' => '2025-01-15 09:00:00',
                'description' => '2024 年第四季全供應鏈 ESG 評核，已結案',
            ],
            [
                'series' => '年度 ESG 全維度評核系列',
                'name'   => '2025 Q1 台灣供應商 ESG 問卷',
                'status' => 'active',
                'due_date' => '2025-03-31',
                'description' => '針對台灣 Tier 1 供應商進行首次 ESG 評估',
            ],
            [
                'series' => '年度 ESG 全維度評核系列',
                'name'   => '2025 Q3 東南亞廠商評核',
                'status' => 'draft',
                'due_date' => '2025-09-30',
                'description' => '覆蓋越南、泰國、馬來西亞供應商',
            ],
            // ISO20400 系列
            [
                'series' => 'ISO 20400 永續採購稽核系列',
                'name'   => '2024 H1 ISO 20400 試行計畫',
                'status' => 'closed',
                'due_date' => '2024-06-30',
                'closed_at' => '2024-07-10 10:00:00',
                'description' => 'ISO 20400 首次試行，已結案',
            ],
            [
                'series' => 'ISO 20400 永續採購稽核系列',
                'name'   => '2025 ISO 20400 永續採購稽核',
                'status' => 'active',
                'due_date' => '2025-06-30',
                'description' => '依 ISO 20400 框架評核所有 Tier 1 廠商',
            ],
            // ISO26000 系列
            [
                'series' => 'ISO 26000 社會責任評核系列',
                'name'   => '2025 ISO 26000 社會責任評核',
                'status' => 'active',
                'due_date' => '2025-08-31',
                'description' => '依 ISO 26000 七大核心主題評核供應商',
            ],
            // Geo-Risk 系列
            [
                'series' => '供應鏈安全與地緣風險評核系列',
                'name'   => '供應鏈安全管理評核 2025',
                'status' => 'active',
                'due_date' => '2025-07-31',
                'description' => '評估高風險地區供應商的安全管理成熟度',
            ],
        ];

        $count = 0;
        foreach ($projectsData as $pd) {
            $series   = $createdSeries[$pd['series']];
            $template = SAQTemplate::find($series->template_id);

            // 確認同系列已有的版本
            $existingVersions = SaqProject::where('series_id', $series->id)
                ->whereNotNull('template_version')
                ->distinct()
                ->pluck('template_version');
            $isComparable = $existingVersions->isEmpty()
                || ($existingVersions->count() === 1 && $existingVersions->first() === $template->version);

            $project = SaqProject::firstOrCreate(
                ['name' => $pd['name']],
                [
                    'template_id'          => $template->id,
                    'template_ref_id'      => $template->id,
                    'template_ref_version' => (int) $template->version ?: 1,
                    'template_version'     => $template->version,
                    'series_id'            => $series->id,
                    'is_comparable'        => $isComparable,
                    'status'               => $pd['status'],
                    'due_date'             => $pd['due_date'] ?? null,
                    'closed_at'            => $pd['closed_at'] ?? null,
                    'description'          => $pd['description'] ?? null,
                ]
            );

            // 若題目快照不存在則建立
            if ($project->projectQuestions()->count() === 0) {
                $snapshotService->snapshot($project, $template);
            }

            $count++;
        }

        $this->command->info("Seeded {$count} SaqProjects across " . count($createdSeries) . " AssessmentSeries.");
    }
}
