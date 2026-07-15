<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 永續倡議問卷完整 DEMO 資料
 * 每個專案發送 10 間供應商，涵蓋填答、回覆、審查歷程
 */
class SaqDemoSeeder extends Seeder
{
    private string $adminId;
    private string $sustainId;
    private array $suppliers      = [];  // code → id
    private array $projects       = [];  // name → id
    private array $projectTpl     = [];  // name → template_id
    private array $bankQuestions  = [];

    public function run(): void
    {
        $this->loadReferences();

        $this->command->info('1/5 建立專案題目...');
        $this->createProjectQuestions();

        $this->command->info('2/5 建立問卷發送記錄（SAQs）...');
        $this->createSaqs();

        $this->command->info('3/5 建立問卷回覆...');
        $this->createResponses();

        $this->command->info('4/5 填入題目得分（raw_score）...');
        $this->fillRawScores();

        $this->command->info('5/5 建立審查歷程...');
        $this->createReviewHistories();

        $this->command->info('DEMO 資料建立完成。');
    }

    // ─────────────────────────────────────────────────────────────────────
    // 載入參照資料
    // ─────────────────────────────────────────────────────────────────────

    private function loadReferences(): void
    {
        $this->adminId   = DB::table('users')->where('email', 'admin@esgchain.com')->value('id');
        $this->sustainId = DB::table('users')->where('email', 'sustain@esgchain.com')->value('id') ?? $this->adminId;

        $this->suppliers = DB::table('suppliers')->pluck('id', 'code')->toArray();

        $rows = DB::table('saq_projects')->get(['id', 'name', 'template_id']);
        foreach ($rows as $r) {
            $this->projects[$r->name]   = $r->id;
            $this->projectTpl[$r->name] = $r->template_id;
        }

        $this->bankQuestions = DB::table('saq_questions')
            ->whereNull('template_id')
            ->orderBy('order')
            ->get()
            ->toArray();
    }

    // ─────────────────────────────────────────────────────────────────────
    // 建立專案題目（SaqProjectSeeder 已 snapshot，此步驟為補漏）
    // ─────────────────────────────────────────────────────────────────────

    private function createProjectQuestions(): void
    {
        $bankIds = array_map(fn($q) => $q->id, $this->bankQuestions);

        $projectQuestionSets = [
            '2025 Q1 台灣供應商 ESG 問卷'    => array_slice($bankIds, 0, 12),
            '2025 ISO 20400 永續採購稽核'      => array_slice($bankIds, 15, 12),
            '2025 ISO 26000 社會責任評核'      => array_slice($bankIds, 5, 12),
            '供應鏈安全管理評核 2025'           => array_slice($bankIds, 25, 10),
            '2025 Q3 東南亞廠商評核'           => array_slice($bankIds, 2, 10),
            '2024 H1 ISO 20400 試行計畫'       => array_slice($bankIds, 15, 10),
            '2024 Q4 年度 ESG 問卷（已結案）'  => array_slice($bankIds, 0, 12),
        ];

        foreach ($projectQuestionSets as $projectName => $bankQuestionIds) {
            if (!isset($this->projects[$projectName])) {
                continue;
            }
            $projectId = $this->projects[$projectName];

            if (DB::table('project_questions')->where('project_id', $projectId)->exists()) {
                continue;
            }

            $order = 1;
            foreach ($bankQuestionIds as $bankQId) {
                $bankQ = collect($this->bankQuestions)->firstWhere('id', $bankQId);
                if (!$bankQ) {
                    continue;
                }

                $type = match ($bankQ->question_type) {
                    'single_choice'   => 'single',
                    'multiple_choice' => 'multiple',
                    'boolean'         => 'boolean',
                    'number'          => 'scale',
                    default           => 'text',
                };

                DB::table('project_questions')->insert([
                    'id'                          => (string) Str::uuid(),
                    'project_id'                  => $projectId,
                    'order'                       => $order++,
                    'question_text'               => $bankQ->question_text,
                    'question_type'               => $type,
                    'options'                     => $bankQ->options,
                    'weight'                      => round(1.0 / count($bankQuestionIds), 4),
                    'is_required'                 => 1,
                    'sasb_topic_id'               => $bankQ->sasb_topic_id ?? null,
                    'sasb_metric_code'            => $bankQ->sasb_metric_code ?? null,
                    'tags'                        => $bankQ->tags ?? null,
                    'source_bank_question_id'     => $bankQId,
                    'source_template_question_id' => null,
                    'created_at'                  => now(),
                    'updated_at'                  => now(),
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // 建立 SAQ 發送記錄（每個專案 10 間供應商）
    // ─────────────────────────────────────────────────────────────────────

    private function createSaqs(): void
    {
        // 格式: [supplierCode, status, score, grade, sentOffset, submittedOffset, scoreE, scoreS, scoreG, finalScore, finalGrade]
        // draft 專案（2025 Q3 東南亞廠商評核）不發送
        $projectPlans = [

            // ── active｜ESG ──────────────────────────────────────────────
            '2025 Q1 台灣供應商 ESG 問卷' => [
                ['CTN-001', 'completed',       91.7, 'A', '-90d', '-60d', 94.2, 90.1, 88.5,  null,  null],
                ['CTN-002', 'completed',       78.3, 'C', '-88d', '-58d', 80.2, 76.1, 77.9,  82.0,  'B'],
                ['GMN-001', 'review_returned', null, null, '-85d', '-50d', null, null, null,  null,  null],
                ['WVN-001', 'submitted',       null, null, '-80d', '-45d', null, null, null,  null,  null],
                ['SYN-001', 'submitted',       null, null, '-78d', '-42d', null, null, null,  null,  null],
                ['CTN-003', 'in_progress',     null, null, '-75d',  null,  null, null, null,  null,  null],
                ['GMN-002', 'in_progress',     null, null, '-72d',  null,  null, null, null,  null,  null],
                ['DYE-001', 'sent',            null, null, '-68d',  null,  null, null, null,  null,  null],
                ['WVN-002', 'sent',            null, null, '-65d',  null,  null, null, null,  null,  null],
                ['TRM-001', 'sent',            null, null, '-62d',  null,  null, null, null,  null,  null],
            ],

            // ── active｜ISO20400 ──────────────────────────────────────────
            '2025 ISO 20400 永續採購稽核' => [
                ['CTN-001', 'completed',       88.5, 'B', '-70d', '-40d', 90.0, 87.2, 86.1,  null,  null],
                ['CHM-001', 'completed',       95.2, 'A', '-68d', '-38d', 96.5, 94.8, 93.9,  null,  null],
                ['GMN-003', 'review_returned', null, null, '-65d', '-35d', null, null, null,  null,  null],
                ['CHM-002', 'submitted',       null, null, '-60d', '-28d', null, null, null,  null,  null],
                ['SYN-002', 'submitted',       null, null, '-58d', '-25d', null, null, null,  null,  null],
                ['TRM-002', 'in_progress',     null, null, '-55d',  null,  null, null, null,  null,  null],
                ['CHM-004', 'in_progress',     null, null, '-52d',  null,  null, null, null,  null,  null],
                ['DYE-002', 'sent',            null, null, '-48d',  null,  null, null, null,  null,  null],
                ['SYN-003', 'sent',            null, null, '-45d',  null,  null, null, null,  null,  null],
                ['WVN-003', 'sent',            null, null, '-42d',  null,  null, null, null,  null,  null],
            ],

            // ── active｜ISO26000 ──────────────────────────────────────────
            '2025 ISO 26000 社會責任評核' => [
                ['GMN-001', 'completed',       82.1, 'B', '-65d', '-35d', 83.5, 81.0, 81.2,  null,  null],
                ['GMN-002', 'completed',       69.4, 'C', '-63d', '-33d', 71.2, 67.8, 68.9,  null,  null],
                ['GMN-004', 'review_returned', null, null, '-60d', '-30d', null, null, null,  null,  null],
                ['GMN-005', 'submitted',       null, null, '-58d', '-22d', null, null, null,  null,  null],
                ['LOG-001', 'submitted',       null, null, '-55d', '-18d', null, null, null,  null,  null],
                ['GMN-003', 'in_progress',     null, null, '-50d',  null,  null, null, null,  null,  null],
                ['GMN-006', 'in_progress',     null, null, '-48d',  null,  null, null, null,  null,  null],
                ['LOG-002', 'sent',            null, null, '-45d',  null,  null, null, null,  null,  null],
                ['WVN-001', 'sent',            null, null, '-42d',  null,  null, null, null,  null,  null],
                ['PKG-001', 'sent',            null, null, '-40d',  null,  null, null, null,  null,  null],
            ],

            // ── active｜Geo-Risk ─────────────────────────────────────────
            '供應鏈安全管理評核 2025' => [
                ['LOG-001', 'completed',       76.8, 'C', '-55d', '-25d', 78.2, 75.5, 76.0,  80.0,  'C'],
                ['CTN-001', 'completed',       88.0, 'B', '-53d', '-23d', 89.5, 87.2, 86.8,  null,  null],
                ['GMN-001', 'review_returned', null, null, '-50d', '-20d', null, null, null,  null,  null],
                ['CHM-001', 'submitted',       null, null, '-48d', '-15d', null, null, null,  null,  null],
                ['WVN-001', 'submitted',       null, null, '-45d', '-12d', null, null, null,  null,  null],
                ['LOG-002', 'in_progress',     null, null, '-42d',  null,  null, null, null,  null,  null],
                ['SYN-001', 'in_progress',     null, null, '-40d',  null,  null, null, null,  null,  null],
                ['TRM-001', 'sent',            null, null, '-38d',  null,  null, null, null,  null,  null],
                ['PKG-001', 'sent',            null, null, '-36d',  null,  null, null, null,  null,  null],
                ['DYE-001', 'sent',            null, null, '-34d',  null,  null, null, null,  null,  null],
            ],

            // ── closed｜ISO20400（歷史試行）──────────────────────────────
            '2024 H1 ISO 20400 試行計畫' => [
                ['CTN-001', 'completed', 86.0, 'B', '-220d', '-190d', 88.0, 84.5, 83.2, null, null],
                ['CHM-001', 'completed', 91.5, 'A', '-218d', '-188d', 93.0, 90.2, 90.8, null, null],
                ['CHM-002', 'completed', 74.3, 'C', '-216d', '-185d', 76.0, 72.5, 73.8, null, null],
                ['SYN-001', 'completed', 80.5, 'B', '-214d', '-182d', 82.0, 79.0, 79.8, null, null],
                ['TRM-001', 'completed', 68.2, 'D', '-212d', '-180d', 70.5, 65.8, 67.0, null, null],
                ['GMN-001', 'completed', 77.9, 'C', '-210d', '-178d', 79.5, 76.2, 77.0, null, null],
                ['DYE-001', 'completed', 63.4, 'D', '-208d', '-175d', 65.0, 61.5, 63.0, null, null],
                ['WVN-001', 'completed', 85.1, 'B', '-206d', '-172d', 87.0, 83.5, 83.8, null, null],
                ['SYN-002', 'review_returned', null, null, '-205d', '-170d', null, null, null, null, null],
                ['CHM-004', 'submitted',  null, null, '-204d', '-165d', null, null, null, null, null],
            ],

            // ── closed｜ESG（年度 ESG）───────────────────────────────────
            '2024 Q4 年度 ESG 問卷（已結案）' => [
                ['CTN-001', 'completed', 85.0, 'B', '-200d', '-170d', 87.0, 83.0, 82.0, null, null],
                ['CTN-002', 'completed', 60.0, 'D', '-200d', '-168d', 58.0, 62.0, 61.5, null, null],
                ['GMN-001', 'completed', 70.0, 'C', '-198d', '-165d', 72.0, 68.0, 69.5, null, null],
                ['GMN-002', 'completed', 68.5, 'C', '-196d', '-162d', 70.0, 65.5, 68.0, null, null],
                ['WVN-001', 'completed', 79.0, 'C', '-195d', '-160d', 80.5, 77.0, 78.5, null, null],
                ['SYN-001', 'completed', 92.0, 'A', '-194d', '-158d', 94.0, 90.5, 91.0, null, null],
                ['DYE-001', 'completed', 55.0, 'D', '-193d', '-155d', 57.0, 52.5, 54.8, null, null],
                ['TRM-001', 'completed', 83.5, 'B', '-192d', '-152d', 85.0, 81.5, 82.8, null, null],
                ['CTN-003', 'review_returned', null, null, '-191d', '-148d', null, null, null, null, null],
                ['WVN-002', 'submitted',  null, null, '-190d', '-145d', null, null, null, null, null],
            ],
        ];

        foreach ($projectPlans as $projectName => $suppliers) {
            if (!isset($this->projects[$projectName])) {
                $this->command->warn("  專案不存在，跳過：{$projectName}");
                continue;
            }

            $projectId  = $this->projects[$projectName];
            $templateId = $this->projectTpl[$projectName];
            $count      = 0;

            foreach ($suppliers as $row) {
                $row = array_pad($row, 11, null);
                [$code, $status, $score, $grade, $sentOff, $subOff, $scoreE, $scoreS, $scoreG, $finalScore, $finalGrade] = $row;

                $supplierId = $this->suppliers[$code] ?? null;
                if (!$supplierId) {
                    $this->command->warn("  供應商 {$code} 不存在，跳過");
                    continue;
                }

                if (DB::table('saqs')->where('project_id', $projectId)->where('supplier_id', $supplierId)->where('template_id', $templateId)->exists()) {
                    continue;
                }

                $sentAt      = $sentOff ? now()->modify(str_replace('d', ' days', $sentOff)) : null;
                $submittedAt = $subOff  ? now()->modify(str_replace('d', ' days', $subOff))  : null;
                $reviewedAt  = in_array($status, ['completed', 'review_returned']) && $submittedAt
                    ? (clone $submittedAt)->modify('+10 days') : null;

                DB::table('saqs')->insert([
                    'id'                => (string) Str::uuid(),
                    'project_id'        => $projectId,
                    'supplier_id'       => $supplierId,
                    'template_id'       => $templateId,
                    'status'            => $status,
                    'score'             => $score,
                    'grade'             => $grade,
                    'score_e'           => $scoreE,
                    'score_s'           => $scoreS,
                    'score_g'           => $scoreG,
                    'final_score'       => $finalScore,
                    'final_grade'       => $finalGrade,
                    'scoring_job_id'    => null,
                    'sent_at'           => $sentAt,
                    'submitted_at'      => $submittedAt,
                    'review_started_at' => $reviewedAt ? (clone $submittedAt)->modify('+3 days') : null,
                    'reviewed_at'       => $reviewedAt,
                    'reviewed_by_id'    => $reviewedAt ? $this->sustainId : null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
                $count++;
            }

            $this->command->info("  {$projectName}：新增 {$count} 筆 SAQ");
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // 建立問卷回覆
    // ─────────────────────────────────────────────────────────────────────

    private function createResponses(): void
    {
        $saqs = DB::table('saqs')
            ->whereIn('status', ['submitted', 'completed', 'review_returned', 'under_review'])
            ->get();

        foreach ($saqs as $saq) {
            $pqs = DB::table('project_questions')
                ->where('project_id', $saq->project_id)
                ->orderBy('order')
                ->get();

            if ($pqs->isEmpty()) {
                continue;
            }

            foreach ($pqs as $pq) {
                $existing = DB::table('saq_responses')
                    ->where('saq_id', $saq->id)
                    ->where('project_question_id', $pq->id)
                    ->first();

                if ($existing) {
                    $isBadSingle = $pq->question_type === 'single'
                        && $existing->answer !== null
                        && $existing->answer_options === null;
                    if (!$isBadSingle) {
                        continue;
                    }
                    DB::table('saq_responses')->where('id', $existing->id)->delete();
                }

                [$answer, $answerOptions] = $this->generateAnswer($pq);

                DB::table('saq_responses')->insert([
                    'id'                  => (string) Str::uuid(),
                    'saq_id'              => $saq->id,
                    'question_id'         => $pq->source_bank_question_id ?? $pq->id,
                    'project_question_id' => $pq->id,
                    'answer'              => $answer,
                    'answer_options'      => $answerOptions ? json_encode($answerOptions) : null,
                    'evidence_note'       => $this->generateEvidenceNote($pq->question_type),
                    'raw_score'           => null,
                    'score_confidence'    => in_array($pq->question_type, ['boolean', 'single', 'multiple']) ? 'high' : 'low',
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }
        }
    }

    private function generateAnswer(object $pq): array
    {
        $options = $pq->options ? json_decode($pq->options, true) : [];

        return match ($pq->question_type) {
            'boolean' => [(bool) array_rand([true => 1, false => 0]) ? '是' : '否', null],
            'single'  => !empty($options)
                ? [null, [$this->optionLabel($options[array_rand($options)])]]
                : ['是', null],
            'multiple' => !empty($options) && count($options) >= 2
                ? [null, array_map(fn($i) => $this->optionLabel($options[$i]), (array) array_rand($options, min(2, count($options))))]
                : [null, []],
            'scale'   => [(string) random_int(50, 9999), null],
            default   => ['已建立相關政策並持續執行中，每年進行內部稽核。', null],
        };
    }

    private function optionLabel(mixed $opt): string
    {
        return is_array($opt) ? ($opt['label'] ?? $opt['value'] ?? '') : (string) $opt;
    }

    private function generateEvidenceNote(string $type): ?string
    {
        if ($type === 'boolean') {
            return null;
        }
        $notes = [
            '已取得第三方認證，最新版認證書附於附件。',
            '詳細資料請參閱公司永續報告書第三章。',
            '執行記錄由 ESG 委員會存檔，可供查驗。',
            '相關政策已於公司官網公告，員工均已簽署知悉同意書。',
            null,
        ];
        return $notes[array_rand($notes)];
    }

    // ─────────────────────────────────────────────────────────────────────
    // 填入題目得分（依答案估算 raw_score = item_score × weight）
    // ─────────────────────────────────────────────────────────────────────

    private function fillRawScores(): void
    {
        $responses = DB::table('saq_responses as r')
            ->join('saqs', 'saqs.id', '=', 'r.saq_id')
            ->join('project_questions as pq', 'pq.id', '=', 'r.project_question_id')
            ->whereIn('saqs.status', ['submitted', 'completed', 'review_returned', 'under_review'])
            ->whereNull('r.raw_score')
            ->select('r.id', 'r.answer', 'r.answer_options', 'pq.question_type', 'pq.weight', 'pq.options')
            ->get();

        foreach ($responses as $r) {
            $itemScore = $this->estimateItemScore($r);
            DB::table('saq_responses')->where('id', $r->id)->update([
                'raw_score'        => round($itemScore * (float) $r->weight, 4),
                'score_confidence' => in_array($r->question_type, ['boolean', 'single', 'single_choice', 'multiple', 'multiple_choice']) ? 'high' : 'medium',
            ]);
        }
    }

    // 0-100 分的題目得分，依答案類型估算
    private function estimateItemScore(object $r): float
    {
        $type = $r->question_type;

        if ($type === 'boolean') {
            return $r->answer === '是' ? 100.0 : 0.0;
        }

        $opts = $r->answer_options ? json_decode($r->answer_options, true) : null;

        if (in_array($type, ['single', 'single_choice']) && is_array($opts) && count($opts)) {
            $standard = ['完全符合' => 100, '大部分符合' => 75, '部分符合' => 50, '少部分符合' => 25, '不符合' => 0];
            $chosen = $opts[0] ?? '';
            if (isset($standard[$chosen])) {
                return (float) $standard[$chosen];
            }
            // 自訂選項：依選項位置估分（第一個最高分）
            $allOpts = $r->options ? json_decode($r->options, true) : [];
            $pos = array_search($chosen, $allOpts);
            if ($pos !== false && count($allOpts) > 1) {
                return 100.0 - ($pos / (count($allOpts) - 1)) * 80.0;
            }
            return 60.0;
        }

        if (in_array($type, ['multiple', 'multiple_choice']) && is_array($opts)) {
            $allOpts = $r->options ? json_decode($r->options, true) : [];
            if (!$allOpts) return 60.0;
            // 選越多越好（正向題），依選擇比例給分
            $ratio = count($allOpts) > 0 ? count($opts) / count($allOpts) : 0.5;
            return min(100.0, 40.0 + $ratio * 60.0);
        }

        // scale / text：給中等分數
        return 65.0;
    }

    // ─────────────────────────────────────────────────────────────────────
    // 建立審查歷程
    // ─────────────────────────────────────────────────────────────────────

    private function createReviewHistories(): void
    {
        $saqs = DB::table('saqs')
            ->whereIn('status', ['submitted', 'completed', 'review_returned', 'under_review', 'sent', 'in_progress'])
            ->get();

        foreach ($saqs as $saq) {
            if ($saq->sent_at && !DB::table('saq_review_histories')->where('saq_id', $saq->id)->where('action', 'send')->exists()) {
                DB::table('saq_review_histories')->insert([
                    'id'         => (string) Str::uuid(),
                    'saq_id'     => $saq->id,
                    'action'     => 'send',
                    'acted_by'   => $this->adminId,
                    'comment'    => '已發送問卷，請供應商於截止日前完成填答。',
                    'created_at' => $saq->sent_at,
                    'updated_at' => $saq->sent_at,
                ]);
            }

            if ($saq->submitted_at && !DB::table('saq_review_histories')->where('saq_id', $saq->id)->where('action', 'submit')->exists()) {
                DB::table('saq_review_histories')->insert([
                    'id'         => (string) Str::uuid(),
                    'saq_id'     => $saq->id,
                    'action'     => 'submit',
                    'acted_by'   => null,
                    'comment'    => '供應商已完成填答並提交問卷。',
                    'created_at' => $saq->submitted_at,
                    'updated_at' => $saq->submitted_at,
                ]);
            }

            if (in_array($saq->status, ['completed', 'review_returned']) && $saq->reviewed_at) {
                $action  = $saq->status === 'completed' ? 'approve' : 'request_revision';
                $comment = $saq->status === 'completed'
                    ? '審查通過。評估結果已更新，請供應商持續改善弱項。'
                    : '部分題目回覆未附佐證文件，請補充後重新提交。';

                if (!DB::table('saq_review_histories')->where('saq_id', $saq->id)->where('action', $action)->exists()) {
                    DB::table('saq_review_histories')->insert([
                        'id'         => (string) Str::uuid(),
                        'saq_id'     => $saq->id,
                        'action'     => $action,
                        'acted_by'   => $this->sustainId,
                        'comment'    => $comment,
                        'created_at' => $saq->reviewed_at,
                        'updated_at' => $saq->reviewed_at,
                    ]);
                }
            }
        }
    }
}
