<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompleteQ1SaqsSeeder extends Seeder
{
    public function run(): void
    {
        $proj = DB::table('saq_projects')->where('name', 'like', '%2026 Q1%')->first();
        if (!$proj) {
            $this->command->error('找不到 2026 Q1 專案');
            return;
        }

        $pendingSaqs = DB::table('saqs')
            ->where('project_id', $proj->id)
            ->where('status', 'in_progress')
            ->get();

        if ($pendingSaqs->isEmpty()) {
            $this->command->info('無待完成 SAQ');
            return;
        }

        $now = Carbon::now();
        $sustainId = DB::table('users')->where('email', 'sustain@esgchain.com')->value('id');
        $positive = ['是', '已建立', '已實施', '符合', '出具'];
        $negative = ['否', '未建立', '部分實施', '不符合', '不適用'];

        foreach ($pendingSaqs as $saq) {
            $score = rand(50, 88);
            $grade = $score >= 85 ? 'A' : ($score >= 70 ? 'B' : ($score >= 55 ? 'C' : 'D'));
            $submittedAt = $now->copy()->subDays(rand(2, 15));

            $dims = [];
            foreach (['E1', 'E2', 'E3', 'E4', 'E5'] as $d) {
                $dims[$d] = max(20, min(100, $score + rand(-15, 15)));
            }

            // 填滿未回覆的 project_questions
            $existingPqIds = DB::table('saq_responses')
                ->where('saq_id', $saq->id)
                ->pluck('project_question_id')
                ->toArray();

            $allPqs = DB::table('project_questions')
                ->where('project_id', $proj->id)
                ->whereNotIn('id', $existingPqIds)
                ->get(['id', 'source_template_question_id']);

            $rows = [];
            foreach ($allPqs as $pq) {
                $isPos = rand(0, 100) < $score;
                $rows[] = [
                    'id'                  => (string) Str::uuid(),
                    'saq_id'              => $saq->id,
                    'question_id'         => $pq->source_template_question_id,
                    'project_question_id' => $pq->id,
                    'answer'              => $isPos
                        ? $positive[array_rand($positive)]
                        : $negative[array_rand($negative)],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('saq_responses')->insert($chunk);
            }

            // 更新 SAQ 狀態與分數
            DB::table('saqs')->where('id', $saq->id)->update([
                'status'       => 'submitted',
                'score'        => $score,
                'grade'        => $grade,
                'dim_e1'       => $dims['E1'],
                'dim_e2'       => $dims['E2'],
                'dim_e3'       => $dims['E3'],
                'dim_e4'       => $dims['E4'],
                'dim_e5'       => $dims['E5'],
                'dim_e6'       => null,
                'submitted_at' => $submittedAt,
                'updated_at'   => $now,
            ]);

            // 審核記錄
            DB::table('saq_review_histories')->insert([
                'id'         => (string) Str::uuid(),
                'saq_id'     => $saq->id,
                'action'     => 'approve',
                'acted_by'   => $sustainId,
                'comment'    => "2026 Q1 複評審核完成。總分 {$score}（等級 {$grade}），E1~E5 六維度分數已計算。",
                'created_at' => $submittedAt->copy()->addDays(2),
                'updated_at' => $submittedAt->copy()->addDays(2),
            ]);

            // 建立或更新對應 RA
            $existingRa = DB::table('risk_assessments')->where('source_saq_id', $saq->id)->first();
            $raData = [
                'dim_e1'      => $dims['E1'],
                'dim_e2'      => $dims['E2'],
                'dim_e3'      => $dims['E3'],
                'dim_e4'      => $dims['E4'],
                'dim_e5'      => $dims['E5'],
                'dim_e6'      => null,
                'assessed_at' => $submittedAt,
                'updated_at'  => $now,
            ];
            if ($existingRa) {
                DB::table('risk_assessments')->where('id', $existingRa->id)->update($raData);
            } else {
                DB::table('risk_assessments')->insert(array_merge($raData, [
                    'id'                 => (string) Str::uuid(),
                    'supplier_id'        => $saq->supplier_id,
                    'assessed_by'        => null,
                    'notes'              => 'DEMO v3 RA — 從 2026 Q1 SAQ 推導',
                    'source_saq_id'      => $saq->id,
                    'source_type'        => 'saq',
                    'source_id'          => $saq->id,
                    'assessment_version' => 'v3',
                    'created_at'         => $now,
                ]));
            }

            $supplierName = DB::table('suppliers')->where('id', $saq->supplier_id)->value('name');
            $this->command->info("✓ {$supplierName} → score={$score} grade={$grade}");
        }

        $this->command->info('2026 Q1 全部完成');
    }
}
