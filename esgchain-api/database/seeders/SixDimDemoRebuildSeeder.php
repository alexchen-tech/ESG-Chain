<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 六維度 DEMO 資料完整重建
 *
 * 1. 清除舊評核資料
 * 2. 指派題庫標籤（60 題 → 對應 L3 標籤）
 * 3. 建立 六維度綜合評核問卷 v1.0 範本
 * 4. 建立 3 個評核系列
 * 5. 建立 3 個評核專案 + SAQ + 完整回覆與評核記錄
 */
class SixDimDemoRebuildSeeder extends Seeder
{
    // 銀行題目 id → 最適 L3 tag id
    private array $questionTagMap = [
        '038b63ce-b150-4eb5-b8d6-be8870b0a2be' => 'a21f0733-4020-4c4b-97ec-481ce994f397',
        '0d267f73-5688-4338-af81-97e729950d45' => 'a21f0733-3554-42e0-bc41-f96625f315fd',
        '0fc7ae5a-22eb-4f70-80d2-a5c0d8609dd7' => 'a21f0733-4360-4660-bb1a-0722348cf3b8',
        '138a35a7-0b6e-4c4d-adec-d890fb56ac36' => 'a21f0733-405f-420a-8084-fbd5ae701c14',
        '158bf694-b9de-4b10-bea8-010e86a6ed5b' => 'a21f0733-3c69-4746-aed0-7defa6b2812d',
        '1651d1c1-7210-4e59-b15a-37745eb74358' => 'a21f0733-4360-4660-bb1a-0722348cf3b8',
        '19d800c2-7f37-47d4-bf3e-3174979a66eb' => 'a21f0733-3f16-4290-b1f4-802be0250916',
        '1ba6d6e0-12fc-420d-a5cd-9fba559ecbbd' => 'a21f0733-2cbc-404e-9765-9abb58bb8ace',
        '20466c48-2b9b-426f-a073-2774c1e40951' => 'a21f0733-3e9b-4281-8e5c-558fe5fc3484',
        '205d5b7f-1a15-4ee4-a0d3-68c5e925e3fe' => 'a21f0733-2761-442d-a06e-bb2d62c31f89',
        '276adbf2-7d31-4a59-ac77-e4e777f480d6' => 'a21f0733-32cf-4b7d-a0a7-9d6cdb665740',
        '283892b1-fb31-48b6-ad6d-d4ecc97c3351' => 'a21f0733-3287-4f30-b756-ad31d60a6235',
        '298c64d0-7e41-4b71-a049-68837043dabc' => 'a21f0733-4179-4860-8a25-e09e47e0ab8f',
        '2a61b17e-1a86-4146-ac72-b2ef497661cb' => 'a21f0733-3dc5-4542-bc6c-312b3c826bbc',
        '2de3dc53-4a2e-4504-9fcb-c94255925bba' => 'a21f0733-3f58-4f03-a944-155e550d3122',
        '2e4fbe31-f81f-4200-b76e-56de063252fb' => 'a21f0733-439e-4ee1-bbdb-56eefa5da206',
        '3a4d4643-1c30-43ff-a6db-61de7d94a870' => 'a21f0733-360d-468a-8bdf-a3ef17ac6282',
        '4447d107-8ece-4ed9-9733-b801b8d734f5' => 'a21f0733-3826-433e-afb6-a1a2e7acc7d0',
        '45064c62-2221-4786-ac1c-80c6a1d9c32d' => 'a21f0733-439e-4ee1-bbdb-56eefa5da206',
        '4e9851f0-c245-46b2-81de-b4d10d266a61' => 'a21f0733-4207-47a9-842b-d396ea192400',
        '50cd66bd-60bf-4fac-94d7-536d439588f2' => 'a21f0733-3fcf-4e8a-adc0-f13228706898',
        '50e83212-8476-4f35-8fe2-bbe1fd7f227b' => 'a21f0733-3170-4234-98a4-ec0935c0b6cc',
        '5414c9be-174b-488d-a0ae-1105ef371077' => 'a21f0733-439e-4ee1-bbdb-56eefa5da206',
        '5c0906e1-e90a-4445-bce4-907f8570886c' => 'a21f0733-3ca7-45bf-9e68-901e7e6d0a6c',
        '62de0abe-c93c-4a51-ab0a-5a406049fd14' => 'a21f0733-2d60-4346-a9a2-c3a8f6bd33b5',
        '62e989e1-5200-4d41-a095-369164529252' => 'a21f0733-41c7-4674-a4d3-f6bc5500954d',
        '694ffc59-91c4-4c06-afc1-7c1d01fc9f78' => 'a21f0733-4207-47a9-842b-d396ea192400',
        '71048822-9d69-4dca-9ece-59ea85d2dd73' => 'a21f0733-2d0c-4718-94b7-30ecb01d485c',
        '7b01d90b-09ff-4223-ab97-0191ba1f0bac' => 'a21f0733-364b-4dde-b1be-8fdeabb5ab90',
        '7bbabab9-5b31-43a2-9ae8-abb908e9b77d' => 'a21f0733-3bf6-4718-9c00-4beccaf43c4d',
        '7f54d8e8-7ae0-4a8c-b8c2-0c0cb668a8bf' => 'a21f0733-409d-40f7-9980-a83a3e55d492',
        '86d9f125-b27d-452e-a853-a4e778525a28' => 'a21f0733-2ac9-4d4d-8847-4fc2618c29b8',
        '8a6a7084-1aa7-4b72-90d2-411ef97f0fc8' => 'a21f0733-2da0-47dd-bde7-687d60d1b362',
        '8ce45099-819a-441f-95cb-1607863a0134' => 'a21f0733-3d86-4786-9157-3abc2bfe4e32',
        '8daa6393-a05b-4c67-9c94-6d720dff8d81' => 'a21f0733-3f94-4c95-915e-390f43bc71ea',
        '95540924-2d46-47ab-8a7d-bcd3ab1c2245' => 'a21f0733-413a-4b22-a640-169f6839bb55',
        '9a6816c1-6118-42ec-bfa6-e05553e959f5' => 'a21f0733-29dd-4d0a-8c8a-e840dd1b6852',
        '9bb2dece-38bf-46cb-8049-739785e20e74' => 'a21f0733-3ed9-4e85-9495-e4451f6a5e2b',
        'a44c9ec8-32bf-4283-b020-42103c207554' => 'a21f0733-3dff-4cfa-9384-0cafad6ac8c8',
        'a9265732-46cd-458f-992e-18f0802ec94f' => 'a21f0733-40f3-4beb-8aca-dc4678c146e9',
        'b3d8b245-16b0-419b-b2cf-1694d26839f3' => 'a21f0733-42f1-4683-ad93-4e5a62ba1d56',
        'b6565ec8-7b3e-4c00-a3ef-014e0625c0ea' => 'a21f0733-42b7-47d9-b018-4347769e614b',
        'b6ac27ef-3cc2-4884-9472-1b758b63b8a1' => 'a21f0733-3afe-4b48-9acf-96cdad78d6fa',
        'b70d6cc6-2ca9-4e8b-b319-5406f4398914' => 'a21f0733-2ec3-4ea6-813d-e30773f676f9',
        'b8bdaebe-8315-47b0-a989-9e065e03adc1' => 'a21f0733-4261-4747-bec8-e48b02cec089',
        'b9586c58-f974-4da9-8cd3-cfc2f4075ef9' => 'a21f0733-3f94-4c95-915e-390f43bc71ea',
        'bbb1f434-937f-4d24-9f70-b94aaf2a60aa' => 'a21f0733-3bb4-466b-86f6-2a16635a37c2',
        'be949ec9-3501-431a-a115-f94762b153bc' => 'a21f0733-3127-4809-a3d7-835c79f4d8f9',
        'c3946370-268a-48d8-8721-6f1324cd50ad' => 'a21f0733-30a2-4600-a87e-a95c42097ee1',
        'c3fd1417-9917-4363-ba5b-caad789c55db' => 'a21f0733-3ca7-45bf-9e68-901e7e6d0a6c',
        'c9dd3c16-c751-4f52-9341-faf26e23b145' => 'a21f0733-3d31-44db-8a98-38a5cb07b29b',
        'd2bd6f7d-a0ab-4942-9ca5-a3f705cd633e' => 'a21f0733-3ce8-46fa-8882-970af7b551eb',
        'd2ebe1d6-4f8d-4635-b844-d7cb3fa11a53' => 'a21f0733-4360-4660-bb1a-0722348cf3b8',
        'd84e1fb2-645c-4c54-8a41-664d70260842' => 'a21f0733-4207-47a9-842b-d396ea192400',
        'dc0a0149-a0cc-45a7-ac3a-225ef79e7382' => 'a21f0733-2f55-41ee-85d8-9a4158b790ce',
        'e1caa8dc-488f-4cb9-be06-a0ff60061271' => 'a21f0733-4020-4c4b-97ec-481ce994f397',
        'e287b995-cc46-4198-b654-1f52f0cf8aee' => 'a21f0733-33ab-4942-95c2-703ba6aac8c9',
        'f2150063-0937-4b86-9019-bf65effb5121' => 'a21f0733-4261-4747-bec8-e48b02cec089',
        'f7d67755-e887-4291-b809-439cbeb3c95b' => 'a21f0733-4207-47a9-842b-d396ea192400',
        'fbc78b52-28c9-455e-bc65-b09445675a45' => 'a21f0733-3c28-48be-9fb8-f5b44d0b2775',
    ];

    public function run(): void
    {
        $this->command->info('=== SixDimDemoRebuildSeeder 開始 ===');
        $this->clearOldData();
        $this->assignQuestionTags();
        $templateId = $this->createTemplate();
        [$seriesGenId, $seriesTextileId, $seriesElecId] = $this->createSeries($templateId);
        $this->createProjectsAndSaqs($seriesGenId, $seriesTextileId, $seriesElecId, $templateId);
        $this->command->info('=== SixDimDemoRebuildSeeder 完成 ===');
    }

    private function clearOldData(): void
    {
        $this->command->info('► 清除舊評核資料...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('saq_review_histories')->truncate();
        DB::table('saq_responses')->truncate();
        DB::table('saq_score_snapshots')->truncate();
        DB::table('saqs')->truncate();
        DB::table('project_questions')->truncate();
        DB::table('saq_projects')->truncate();
        DB::table('assessment_series')->truncate();
        DB::table('saq_questions')->whereNotNull('template_id')->delete();
        DB::table('saq_templates')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->command->info('  ✓ 清除完畢');
    }

    private function assignQuestionTags(): void
    {
        $this->command->info('► 指派題庫標籤...');
        DB::table('question_tag_assignments')->truncate();
        $rows = [];
        foreach ($this->questionTagMap as $questionId => $tagId) {
            $rows[] = [
                'question_id' => $questionId,
                'tag_id'      => $tagId,
            ];
        }
        DB::table('question_tag_assignments')->insert($rows);
        $this->command->info('  ✓ 指派 '.count($rows).' 筆標籤');
    }

    private function createTemplate(): string
    {
        $this->command->info('► 建立評核範本...');
        $now = now();
        $adminId = DB::table('users')->where('email', 'admin@esgchain.com')->value('id');

        $templateId = (string) Str::uuid();
        DB::table('saq_templates')->insert([
            'id'          => $templateId,
            'name'        => '六維度綜合評核問卷 v1.0',
            'version'     => '1.0',
            'status'      => 'published',
            'is_active'   => 1,
            'description' => '涵蓋 ESG（E1）、ISO 20400 永續採購（E2）、ISO 26000 社會責任（E3）、地緣政治風險（E4）、ISO 28000 供應鏈安全（E5）五大維度，共 60 題之全方位供應商評核問卷。',
            'created_by_id' => $adminId,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        // 複製銀行題目 → 範本題目
        $bankQuestions = DB::table('saq_questions')->whereNull('template_id')->get();
        foreach ($bankQuestions as $i => $bq) {
            DB::table('saq_questions')->insert([
                'id'                    => (string) Str::uuid(),
                'template_id'           => $templateId,
                'question_text'         => $bq->question_text,
                'question_type'         => $bq->question_type,
                'options'               => $bq->options,
                'weight'                => $bq->weight ?? 1.0,
                'order'                 => $i + 1,
                'is_required'           => $bq->is_required ?? 1,
                'scoring_direction'     => $bq->scoring_direction,
                'scoring_type'          => $bq->scoring_type,
                'option_scores'         => $bq->option_scores,
                'tags'                  => $bq->tags,
                'framework_pillar'      => $bq->framework_pillar,
                'source_bank_question_id' => $bq->id,
                'created_at'            => $now,
                'updated_at'            => $now,
            ]);
        }

        $this->command->info('  ✓ 範本建立完成，含 '.$bankQuestions->count().' 題');
        return $templateId;
    }

    private function createSeries(string $templateId): array
    {
        $this->command->info('► 建立評核系列...');
        $now = now();
        $adminId = DB::table('users')->where('email', 'admin@esgchain.com')->value('id');

        $defaultWeights = json_encode(['E1'=>0.25,'E2'=>0.15,'E3'=>0.20,'E4'=>0.15,'E5'=>0.10,'E6'=>0.15]);
        $gradeThresholds = json_encode(['A'=>85,'B'=>70,'C'=>55,'D'=>0]);

        $seriesGenId = (string) Str::uuid();
        DB::table('assessment_series')->insert([
            'id'                    => $seriesGenId,
            'code'                  => 'GEN-2025',
            'name'                  => '2025 年度全供應商六維度評核',
            'description'           => '適用全體供應商的年度綜合評核，採用系統預設六維度加權（E1:25% E2:15% E3:20% E4:15% E5:10% E6:15%）。',
            'template_id'           => $templateId,
            'status'                => 'active',
            'dim_weights'           => $defaultWeights,
            'dim_weights_source'    => 'default',
            'e4_objective_ratio'    => 0.40,
            'pillar_weights'        => null,
            'grade_thresholds'      => $gradeThresholds,
            'created_by_id'         => $adminId,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);

        $seriesTextileId = (string) Str::uuid();
        DB::table('assessment_series')->insert([
            'id'                    => $seriesTextileId,
            'code'                  => 'TXT-2025',
            'name'                  => '2025 年度紡織製造供應商評核',
            'description'           => '專為紡織製造類供應商設計，加重社會責任（E3:30%）與永續採購（E2:20%），反映紡織產業人權與勞工高風險。',
            'template_id'           => $templateId,
            'status'                => 'active',
            'dim_weights'           => json_encode(['E1'=>0.20,'E2'=>0.20,'E3'=>0.30,'E4'=>0.10,'E5'=>0.10,'E6'=>0.10]),
            'dim_weights_source'    => 'custom',
            'e4_objective_ratio'    => 0.40,
            'pillar_weights'        => null,
            'grade_thresholds'      => $gradeThresholds,
            'created_by_id'         => $adminId,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);

        $seriesElecId = (string) Str::uuid();
        DB::table('assessment_series')->insert([
            'id'                    => $seriesElecId,
            'code'                  => 'ELC-2025',
            'name'                  => '2025 年度電子零組件供應商評核',
            'description'           => '針對電子類供應商，加重地緣政治韌性（E4:20%）與產品合規（E6:20%），對應半導體出口管制與 RoHS/REACH 壓力。',
            'template_id'           => $templateId,
            'status'                => 'active',
            'dim_weights'           => json_encode(['E1'=>0.15,'E2'=>0.15,'E3'=>0.15,'E4'=>0.20,'E5'=>0.15,'E6'=>0.20]),
            'dim_weights_source'    => 'custom',
            'e4_objective_ratio'    => 0.60,
            'pillar_weights'        => null,
            'grade_thresholds'      => $gradeThresholds,
            'created_by_id'         => $adminId,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);

        $this->command->info('  ✓ 3 個評核系列建立完成');
        return [$seriesGenId, $seriesTextileId, $seriesElecId];
    }

    private function createProjectsAndSaqs(
        string $seriesGenId,
        string $seriesTextileId,
        string $seriesElecId,
        string $templateId
    ): void {
        $this->command->info('► 建立評核專案與 SAQ...');
        $now = now();
        $sustainId = DB::table('users')->where('email', 'sustain@esgchain.com')->value('id');

        // 取範本題目
        $tmplQuestions = DB::table('saq_questions')
            ->where('template_id', $templateId)
            ->orderBy('order')
            ->get();

        // 僅取在線（未刪除）供應商
        $activeSuppliers = DB::table('suppliers')
            ->whereNull('deleted_at')
            ->get(['id', 'code']);

        // ── 專案 1：2025 H2 全供應商六維度初評（舊輪）────────────────
        $proj1Id = (string) Str::uuid();
        $pastNow = Carbon::parse('2025-12-20'); // H2 截止日附近，確保年份為 2025
        DB::table('saq_projects')->insert([
            'id'          => $proj1Id,
            'series_id'   => $seriesGenId,
            'template_id' => $templateId,
            'name'        => '2025 H2 全供應商六維度初評',
            'description' => '年度全廠商六維度初始基線評核，供永續團隊建立供應鏈風險基準。',
            'status'      => 'closed',
            'due_date'    => '2025-12-31',
            'start_date'  => '2025-07-01',
            'created_at'  => $pastNow,
            'updated_at'  => $pastNow,
        ]);
        $this->insertProjectQuestions($proj1Id, $tmplQuestions, $pastNow);
        $pqMap1 = $this->buildPqMap($proj1Id);

        foreach ($activeSuppliers as $supplier) {
            $score = rand(45, 88);
            $this->createSaqWithResponses($proj1Id, $supplier->id, $templateId, $pqMap1, $sustainId, $score, 'submitted', $pastNow);
        }
        $this->command->info('  ✓ 專案1：'.count($activeSuppliers).' 筆 SAQ');

        // ── 專案 2：2026 Q1 全供應商六維度複評（新輪）────────────────
        $proj2Id = (string) Str::uuid();
        DB::table('saq_projects')->insert([
            'id'          => $proj2Id,
            'series_id'   => $seriesGenId,
            'template_id' => $templateId,
            'name'        => '2026 Q1 全供應商六維度複評',
            'description' => '2026 年第一季全供應商六維度複評，追蹤矯正行動成效並更新風險基準。',
            'status'      => 'active',
            'due_date'    => '2026-03-31',
            'start_date'  => '2026-01-01',
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $this->insertProjectQuestions($proj2Id, $tmplQuestions, $now);
        $pqMap2 = $this->buildPqMap($proj2Id);

        // 2026 Q1：部分已回覆（submitted）、部分進行中
        $statuses = ['submitted', 'submitted', 'submitted', 'in_progress'];
        foreach ($activeSuppliers as $idx => $supplier) {
            $statusChoice = $statuses[$idx % count($statuses)];
            $score = $statusChoice === 'submitted' ? rand(50, 95) : null;
            $this->createSaqWithResponses($proj2Id, $supplier->id, $templateId, $pqMap2, $sustainId, $score, $statusChoice, $now);
        }
        $this->command->info('  ✓ 專案2：'.count($activeSuppliers).' 筆 SAQ');

        $this->command->info('  ✓ 2 個評核專案建立完成，每家供應商各 2 筆問卷');
    }

    /**
     * 回傳 [source_template_question_id => [pq_id, template_question_id]] map
     */
    private function buildPqMap(string $projectId): array
    {
        $rows = DB::table('project_questions')
            ->where('project_id', $projectId)
            ->get(['id', 'source_template_question_id']);

        $map = [];
        foreach ($rows as $r) {
            $map[$r->source_template_question_id] = $r->id;
        }
        return $map;
    }

    private function insertProjectQuestions(string $projectId, $tmplQuestions, $now = null): void
    {
        $now = $now ?? now();
        $rows = [];
        foreach ($tmplQuestions as $q) {
            $rows[] = [
                'id'                          => (string) Str::uuid(),
                'project_id'                  => $projectId,
                'order'                       => $q->order,
                'question_text'               => $q->question_text,
                'question_type'               => $q->question_type,
                'options'                     => $q->options,
                'weight'                      => $q->weight,
                'is_required'                 => $q->is_required,
                'scoring_direction'           => $q->scoring_direction,
                'scoring_type'                => $q->scoring_type,
                'option_scores'               => $q->option_scores,
                'tags'                        => $q->tags,
                'source_bank_question_id'     => $q->source_bank_question_id,
                'source_template_question_id' => $q->id,
                'is_sasb_required'            => 0,
                'created_at'                  => $now,
                'updated_at'                  => $now,
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('project_questions')->insert($chunk);
        }
    }

    private function createSaqWithResponses(
        string $projectId,
        string $supplierId,
        string $templateId,
        $pqMap,
        ?string $reviewerId,
        ?int $score,
        string $status,
        $now
    ): void {
        $saqId = (string) Str::uuid();
        $submittedAt = in_array($status, ['submitted','reviewed','approved'])
            ? Carbon::parse($now)->subDays(rand(3, 25))
            : null;

        // 分散六維度分數（總和約等於 overall score）
        $dimScores = null;
        if ($score !== null) {
            $base = $score;
            $dims = [];
            $remain = 0;
            foreach (['E1','E2','E3','E4','E5'] as $d) {
                $v = max(20, min(100, $base + rand(-15, 15)));
                $dims[$d] = $v;
                $remain += $v;
            }
            $dims['E6'] = null; // E6 通常需要額外資料，DEMO 留空
            $dimScores = $dims;
        }

        DB::table('saqs')->insert([
            'id'           => $saqId,
            'project_id'   => $projectId,
            'supplier_id'  => $supplierId,
            'template_id'  => $templateId,
            'status'       => $status,
            'score'        => $score,
            'dim_e1'       => $dimScores['E1'] ?? null,
            'dim_e2'       => $dimScores['E2'] ?? null,
            'dim_e3'       => $dimScores['E3'] ?? null,
            'dim_e4'       => $dimScores['E4'] ?? null,
            'dim_e5'       => $dimScores['E5'] ?? null,
            'dim_e6'       => $dimScores['E6'] ?? null,
            'sent_at'      => Carbon::parse($now)->subDays(rand(1, 5)),
            'submitted_at' => $submittedAt,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        if ($status === 'sent') {
            return;
        }

        // 填入回覆（in_progress 填半數題目，submitted 填全部）
        $positiveOptions = ['是', '已建立', '已實施', '符合', '出具'];
        $negativeOptions = ['否', '未建立', '部分實施', '不符合', '不適用'];

        $responseRows = [];
        $pqEntries = collect($pqMap)->keys()->values();
        $fillCount = $status === 'in_progress' ? (int)($pqEntries->count() * 0.5) : $pqEntries->count();

        foreach ($pqEntries->slice(0, $fillCount) as $tmplQId) {
            $pqId = $pqMap[$tmplQId] ?? null;
            if (!$pqId) continue;

            // 根據 score 偏向正向選項
            $isPositive = $score !== null ? (rand(0, 100) < $score) : (rand(0, 1) === 1);
            $answer = $isPositive
                ? $positiveOptions[array_rand($positiveOptions)]
                : $negativeOptions[array_rand($negativeOptions)];

            $responseRows[] = [
                'id'                  => (string) Str::uuid(),
                'saq_id'              => $saqId,
                'question_id'         => $tmplQId,    // template saq_question id (NOT NULL)
                'project_question_id' => $pqId,
                'answer'              => $answer,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }

        if ($responseRows) {
            foreach (array_chunk($responseRows, 200) as $chunk) {
                DB::table('saq_responses')->insert($chunk);
            }
        }

        // 審核記錄（submitted 狀態，約一半有審核）
        if ($status === 'submitted' && $reviewerId && rand(0, 1)) {
            $grade = $score >= 85 ? 'A' : ($score >= 70 ? 'B' : ($score >= 55 ? 'C' : 'D'));
            DB::table('saq_review_histories')->insert([
                'id'       => (string) Str::uuid(),
                'saq_id'   => $saqId,
                'action'   => 'approve',
                'acted_by' => $reviewerId,
                'comment'  => "六維度評核審核完成。總分 {$score}（等級 {$grade}），E1~E5 維度分數已計算。",
                'created_at' => $submittedAt?->addDays(2) ?? $now,
                'updated_at' => $submittedAt?->addDays(2) ?? $now,
            ]);
        }
    }
}
