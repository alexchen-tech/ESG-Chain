<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 補種兩件事：
 * 1. 已完成但沒有 saq_responses 的問卷 → 補填題目回覆
 * 2. 揭露資料中 boolean 類型欄位 → 連結 source_saq_id 到最新已完成問卷
 */
class SaqResponseBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('1/2 補填空白問卷回覆...');
        $this->backfillResponses();

        $this->command->info('2/2 連結揭露 KPI 到問卷來源...');
        $this->linkDisclosureToSaq();

        $this->command->info('完成！');
    }

    // ─────────────────────────────────────────────────────────────────
    // 1. 補填 responses
    // ─────────────────────────────────────────────────────────────────

    private function backfillResponses(): void
    {
        // 找出有分數但沒有 responses 的 SAQ
        $emptySaqs = DB::table('saqs')
            ->whereIn('status', ['completed', 'under_review', 'submitted'])
            ->whereNotNull('score')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('saq_responses')
                  ->whereColumn('saq_responses.saq_id', 'saqs.id');
            })
            ->get(['id', 'project_id', 'score']);

        $this->command->info('  找到 ' . count($emptySaqs) . ' 份空白問卷');

        foreach ($emptySaqs as $saq) {
            $pqs = DB::table('project_questions')
                ->where('project_id', $saq->project_id)
                ->orderBy('order')
                ->get();

            if ($pqs->isEmpty()) continue;

            // 依目標 score 決定正答比例（boolean: 是/否）
            $targetPositiveRate = ($saq->score ?? 70) / 100;

            $rows = [];
            foreach ($pqs as $idx => $pq) {
                [$answer, $answerOptions] = $this->generateAnswer($pq, $targetPositiveRate, $idx);

                $rows[] = [
                    'id'                  => (string) Str::uuid(),
                    'saq_id'              => $saq->id,
                    'question_id'         => $pq->source_bank_question_id ?? $pq->id,
                    'project_question_id' => $pq->id,
                    'answer'              => $answer,
                    'answer_options'      => $answerOptions ? json_encode($answerOptions) : null,
                    'evidence_note'       => $this->generateNote($pq->question_type),
                    'raw_score'           => null,
                    'score_confidence'    => in_array($pq->question_type, ['boolean', 'single_choice']) ? 'high' : 'medium',
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];
            }

            if ($rows) {
                DB::table('saq_responses')->insert($rows);
            }
        }

        // 補算 raw_score
        $this->fillRawScores();
    }

    private function generateAnswer(object $pq, float $positiveRate, int $idx): array
    {
        $options = $pq->options ? json_decode($pq->options, true) : [];

        if ($pq->question_type === 'boolean') {
            // 依正答率決定是否回「是」
            $isPositive = ($idx % 100) < ($positiveRate * 100);
            return [$isPositive ? '是' : '否', null];
        }

        if ($pq->question_type === 'single_choice') {
            if (!empty($options)) {
                // 依正答率選前段選項（通常正面答案排前面）
                $pick = $positiveRate > 0.5
                    ? 0
                    : min(count($options) - 1, 1);
                $label = is_array($options[$pick]) ? ($options[$pick]['label'] ?? $options[$pick]['value'] ?? '') : (string) $options[$pick];
                return [null, [$label]];
            }
            return ['是', null];
        }

        return ['已建立相關政策並持續執行。', null];
    }

    private function generateNote(string $type): ?string
    {
        if ($type === 'boolean') return null;
        $notes = [
            '已取得第三方認證，認證書附於附件。',
            '詳細資料請參閱公司永續報告書。',
            '執行記錄由 ESG 委員會存檔，可供查驗。',
            '相關政策已於公司官網公告。',
            null,
        ];
        return $notes[array_rand($notes)];
    }

    private function fillRawScores(): void
    {
        $responses = DB::table('saq_responses as r')
            ->join('saqs', 'saqs.id', '=', 'r.saq_id')
            ->join('project_questions as pq', 'pq.id', '=', 'r.project_question_id')
            ->whereIn('saqs.status', ['submitted', 'completed', 'under_review'])
            ->whereNull('r.raw_score')
            ->select('r.id', 'r.answer', 'r.answer_options', 'pq.question_type', 'pq.weight', 'pq.option_scores', 'pq.scoring_direction')
            ->get();

        foreach ($responses as $r) {
            $score = $this->estimateScore($r);
            DB::table('saq_responses')->where('id', $r->id)->update([
                'raw_score'        => round($score * (float) $r->weight, 4),
                'score_confidence' => in_array($r->question_type, ['boolean', 'single_choice']) ? 'high' : 'medium',
            ]);
        }
    }

    private function estimateScore(object $r): float
    {
        if ($r->question_type === 'boolean') {
            return $r->answer === '是' ? 100.0 : 0.0;
        }

        if (in_array($r->question_type, ['single_choice', 'single'])) {
            $optionScores = $r->option_scores ? json_decode($r->option_scores, true) : [];
            $answerOptions = $r->answer_options ? json_decode($r->answer_options, true) : [];
            $picked = $answerOptions[0] ?? $r->answer ?? '';
            if (!empty($optionScores) && isset($optionScores[$picked])) {
                return (float) $optionScores[$picked];
            }
            return 60.0;
        }

        return 70.0;
    }

    // ─────────────────────────────────────────────────────────────────
    // 2. 連結 boolean 揭露欄位 → source_saq_id
    // ─────────────────────────────────────────────────────────────────

    private function linkDisclosureToSaq(): void
    {
        // boolean 揭露欄位（可從問卷回答推導）
        $booleanSlugs = [
            'cert.iso14001', 'cert.iso45001', 'cert.iso9001',
            'governance.has_anti_corruption_policy', 'governance.has_esg_report',
            'labor.child_labor_banned', 'supply_chain.supplier_audit_conducted',
        ];

        // 每位供應商最新的已完成問卷（有 responses 的優先）
        $suppliers = DB::table('supplier_disclosures')
            ->whereIn('field_slug', $booleanSlugs)
            ->whereNull('source_saq_id')
            ->distinct()
            ->pluck('supplier_id');

        foreach ($suppliers as $supplierId) {
            // 最新已完成且有 responses 的 SAQ
            $latestSaq = DB::table('saqs')
                ->whereIn('status', ['completed', 'under_review'])
                ->where('supplier_id', $supplierId)
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('saq_responses')
                      ->whereColumn('saq_responses.saq_id', 'saqs.id');
                })
                ->orderByDesc('submitted_at')
                ->value('id');

            if (!$latestSaq) continue;

            DB::table('supplier_disclosures')
                ->where('supplier_id', $supplierId)
                ->whereIn('field_slug', $booleanSlugs)
                ->whereNull('source_saq_id')
                ->update(['source_saq_id' => $latestSaq]);
        }
    }
}
