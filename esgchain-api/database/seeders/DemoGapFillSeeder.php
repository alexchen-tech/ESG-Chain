<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 補齊尚無任何 Seeder 覆蓋的業務資料表：
 * customers/customer_contacts、supplier_facilities/activity_data_reports、
 * chemicals/material_item_chemicals/chemical_compliance_alerts、
 * pcf_requests/pcf_request_lines/pcf_tasks、caps/cap_findings、
 * supplier_status_histories、supplier_disclosures、
 * assessment_series_weights、saq_score_snapshots、saq_response_reviews
 */
class DemoGapFillSeeder extends Seeder
{
    public function run(): void
    {
        $adminId   = DB::table('users')->where('email', 'admin@esgchain.com')->value('id');
        $sustainId = DB::table('users')->where('email', 'sustain@esgchain.com')->value('id') ?? $adminId;

        $this->seedCustomers();
        $this->seedSupplierFacilities();
        $this->seedChemicals();
        $this->seedPcfRequests($adminId);
        $this->seedCaps($adminId);
        $this->seedSupplierStatusHistories($adminId);
        $this->seedSupplierDisclosures();
        $this->seedAssessmentSeriesWeights();
        $this->seedSaqScoreSnapshots();
        $this->seedSaqResponseReviews($sustainId);
        $this->seedPermissions();
        $this->seedSaqTemplateIndustries();
        $this->seedSupplierImports();
        $this->seedFrameworkInfraTables($adminId);

        $this->command->info('DemoGapFillSeeder 完成。');
    }

    /** customers + customer_contacts — 名稱須與 TradeGoodSeeder 的查找一致 */
    private function seedCustomers(): void
    {
        $customers = [
            ['code' => 'CUST-DE-001', 'name' => 'Acme GmbH',        'country_code' => 'DE', 'customer_type' => 'brand',       'contact' => ['name' => 'Markus Weber',  'email' => 'markus.weber@acme-gmbh.de',  'title' => 'Sourcing Director']],
            ['code' => 'CUST-DE-002', 'name' => 'Test GmbH',         'country_code' => 'DE', 'customer_type' => 'distributor', 'contact' => ['name' => 'Lena Hoffmann', 'email' => 'lena.hoffmann@test-gmbh.de', 'title' => 'Procurement Manager']],
            ['code' => 'CUST-UK-001', 'name' => 'Sales Agent Ltd',   'country_code' => 'GB', 'customer_type' => 'agent',       'contact' => ['name' => 'James Carter',  'email' => 'james.carter@salesagent.co.uk', 'title' => 'Account Manager']],
        ];

        foreach ($customers as $c) {
            $contact = $c['contact'];
            unset($c['contact']);

            $existing = DB::table('customers')->where('code', $c['code'])->first();
            if ($existing) {
                $customerId = $existing->id;
            } else {
                $customerId = (string) Str::uuid();
                DB::table('customers')->insert(array_merge($c, [
                    'id'         => $customerId,
                    'status'     => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }

            $contactExists = DB::table('customer_contacts')
                ->where('customer_id', $customerId)
                ->where('email', $contact['email'])
                ->exists();
            if (!$contactExists) {
                DB::table('customer_contacts')->insert([
                    'id'          => (string) Str::uuid(),
                    'customer_id' => $customerId,
                    'name'        => $contact['name'],
                    'email'       => $contact['email'],
                    'title'       => $contact['title'],
                    'is_primary'  => 1,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }

        $this->command->info('✓ customers + customer_contacts 已植入');
    }

    /** supplier_facilities + activity_data_reports */
    private function seedSupplierFacilities(): void
    {
        $suppliers = DB::table('suppliers')->limit(6)->get(['id', 'code', 'country_code', 'name']);
        $count     = 0;

        foreach ($suppliers as $s) {
            $facilityId = (string) Str::uuid();
            $exists     = DB::table('supplier_facilities')->where('supplier_id', $s->id)->exists();
            if ($exists) continue;

            DB::table('supplier_facilities')->insert([
                'id'            => $facilityId,
                'supplier_id'   => $s->id,
                'name'          => $s->name . ' 第一廠',
                'country'       => $s->country_code,
                'address'       => null,
                'facility_type' => 'manufacturing',
                'energy_types'  => json_encode(['electricity', 'natural_gas']),
                'main_products' => json_encode(['紡織原料']),
                'is_active'     => 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            foreach (['2024-Q3', '2024-Q4'] as $period) {
                DB::table('activity_data_reports')->insert([
                    'id'                   => (string) Str::uuid(),
                    'supplier_facility_id' => $facilityId,
                    'report_period'        => $period,
                    'electricity_kwh'      => 45000 + ($count * 1200),
                    'natural_gas_gj'       => 320 + ($count * 15),
                    'water_m3'             => 1800 + ($count * 50),
                    'status'               => 'submitted',
                    'submitted_at'         => now()->subDays(30),
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }

            $count++;
        }

        $this->command->info("✓ supplier_facilities + activity_data_reports 已植入（{$count} 間廠）");
    }

    /** chemicals + material_item_chemicals + chemical_compliance_alerts */
    private function seedChemicals(): void
    {
        $chemicals = [
            ['cas_no' => '117-81-7',  'substance_name' => 'DEHP（鄰苯二甲酸二（2-乙基己基）酯）', 'regulated_lists' => ['REACH_SVHC', 'RoHS']],
            ['cas_no' => '50-00-0',   'substance_name' => '甲醛（Formaldehyde）',                  'regulated_lists' => ['REACH_SVHC']],
            ['cas_no' => '7440-43-9', 'substance_name' => '鎘（Cadmium）',                          'regulated_lists' => ['RoHS', 'REACH_SVHC']],
        ];

        $chemIds = [];
        foreach ($chemicals as $c) {
            $existing = DB::table('chemicals')->where('cas_no', $c['cas_no'])->value('id');
            if ($existing) {
                $chemIds[$c['cas_no']] = $existing;
                continue;
            }
            $id = (string) Str::uuid();
            DB::table('chemicals')->insert([
                'id'              => $id,
                'cas_no'          => $c['cas_no'],
                'substance_name'  => $c['substance_name'],
                'regulated_lists' => json_encode($c['regulated_lists']),
                'svhc_date'       => '2023-01-17',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            $chemIds[$c['cas_no']] = $id;
        }

        $materialItems = DB::table('material_items')->limit(3)->get(['id']);
        $alertCount    = 0;

        foreach ($materialItems as $i => $mi) {
            $casNo = array_keys($chemIds)[$i % count($chemIds)];

            $exists = DB::table('material_item_chemicals')
                ->where('material_item_id', $mi->id)
                ->where('cas_no', $casNo)
                ->exists();
            if ($exists) continue;

            $micId = (string) Str::uuid();
            DB::table('material_item_chemicals')->insert([
                'id'                  => $micId,
                'material_item_id'    => $mi->id,
                'cas_no'              => $casNo,
                'weight_percentage'   => 0.08,
                'reporting_threshold' => 0.1,
                'source'              => 'buyer_input',
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            DB::table('chemical_compliance_alerts')->insert([
                'id'                       => (string) Str::uuid(),
                'material_item_id'         => $mi->id,
                'material_item_chemical_id' => $micId,
                'chemical_id'              => $chemIds[$casNo],
                'regulated_list'           => 'REACH_SVHC',
                'alert_level'              => 'warning',
                'status'                   => 'open',
                'notes'                    => '檢出濃度低於申報門檻，持續監控',
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);
            $alertCount++;
        }

        $this->command->info("✓ chemicals + material_item_chemicals + chemical_compliance_alerts 已植入（{$alertCount} 筆警示）");
    }

    /** pcf_requests + pcf_request_lines */
    private function seedPcfRequests(?string $adminId): void
    {
        $bomLines = DB::table('product_bom_lines')
            ->join('bom_line_suppliers', 'bom_line_suppliers.bom_line_id', '=', 'product_bom_lines.id')
            ->whereNotNull('product_bom_lines.material_item_id')
            ->limit(4)
            ->get([
                'product_bom_lines.id as bom_line_id',
                'product_bom_lines.material_name',
                'product_bom_lines.hs_code',
                'product_bom_lines.material_item_id',
                'bom_line_suppliers.supplier_id',
            ]);

        $bySupplier = $bomLines->groupBy('supplier_id');
        $count      = 0;

        foreach ($bySupplier as $supplierId => $lines) {
            $exists = DB::table('pcf_requests')->where('supplier_id', $supplierId)->exists();
            if ($exists) continue;

            $requestId = (string) Str::uuid();
            DB::table('pcf_requests')->insert([
                'id'             => $requestId,
                'supplier_id'    => $supplierId,
                'period_start'   => '2025-01-01',
                'period_end'     => '2025-03-31',
                'due_date'       => '2025-04-15',
                'status'         => 'pending',
                'trigger_source' => 'buyer_manual',
                'created_by'     => $adminId,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            foreach ($lines as $line) {
                DB::table('pcf_request_lines')->insert([
                    'id'              => (string) Str::uuid(),
                    'pcf_request_id'  => $requestId,
                    'material_item_id' => $line->material_item_id,
                    'bom_line_id'     => $line->bom_line_id,
                    'material_name'   => $line->material_name,
                    'hs_code'         => $line->hs_code,
                    'status'          => 'pending',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            $count++;
        }

        // pcf_tasks：模擬非同步批量計算任務記錄
        if (!DB::table('pcf_tasks')->exists()) {
            $supplierIds = DB::table('suppliers')->limit(3)->pluck('id')->toArray();
            DB::table('pcf_tasks')->insert([
                [
                    'id'              => (string) Str::uuid(),
                    'celery_task_id'  => (string) Str::uuid(),
                    'supplier_ids'    => json_encode($supplierIds),
                    'status'          => 'completed',
                    'progress'        => 100,
                    'result_count'    => count($supplierIds),
                    'created_by'      => $adminId,
                    'created_at'      => now()->subDays(2),
                    'updated_at'      => now()->subDays(2),
                ],
                [
                    'id'              => (string) Str::uuid(),
                    'celery_task_id'  => (string) Str::uuid(),
                    'supplier_ids'    => json_encode($supplierIds),
                    'status'          => 'pending',
                    'progress'        => 0,
                    'result_count'    => null,
                    'created_by'      => $adminId,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ],
            ]);
        }

        $this->command->info("✓ pcf_requests + pcf_request_lines + pcf_tasks 已植入（{$count} 筆請求）");
    }

    /** caps + cap_findings — 針對低分 SAQ 的供應商建立矯正行動 */
    private function seedCaps(?string $adminId): void
    {
        $lowScoreSaqs = DB::table('saqs')
            ->whereIn('grade', ['C', 'D'])
            ->limit(4)
            ->get(['id', 'supplier_id', 'grade']);

        $count = 0;
        foreach ($lowScoreSaqs as $saq) {
            $exists = DB::table('caps')->where('saq_id', $saq->id)->exists();
            if ($exists) continue;

            $capId = (string) Str::uuid();
            DB::table('caps')->insert([
                'id'          => $capId,
                'supplier_id' => $saq->supplier_id,
                'saq_id'      => $saq->id,
                'source_type' => 'saq',
                'source_id'   => $saq->id,
                'title'       => "問卷評核 {$saq->grade} 級矯正行動",
                'description' => '依問卷評核結果，要求供應商針對低分項目提出改善計畫',
                'status'      => 'open',
                'priority'    => $saq->grade === 'D' ? 'high' : 'medium',
                'due_date'    => now()->addDays(45)->toDateString(),
                'created_by'  => $adminId,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            DB::table('cap_findings')->insert([
                'id'                => (string) Str::uuid(),
                'cap_id'            => $capId,
                'category'          => 'labor',
                'finding'           => '勞動安全衛生管理系統文件不完整，未提供近一年內部稽核記錄',
                'corrective_action' => '要求供應商於 30 日內提交完整 OSH 管理文件與最近稽核報告',
                'status'            => 'open',
                'target_date'       => now()->addDays(30)->toDateString(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            $count++;
        }

        $this->command->info("✓ caps + cap_findings 已植入（{$count} 筆矯正行動）");
    }

    /** supplier_status_histories */
    private function seedSupplierStatusHistories(?string $adminId): void
    {
        $suppliers = DB::table('suppliers')->limit(8)->get(['id', 'status']);
        $count     = 0;

        foreach ($suppliers as $s) {
            $exists = DB::table('supplier_status_histories')->where('supplier_id', $s->id)->exists();
            if ($exists) continue;

            DB::table('supplier_status_histories')->insert([
                'id'          => (string) Str::uuid(),
                'supplier_id' => $s->id,
                'from_status' => null,
                'to_status'   => 'active',
                'reason'      => '初次審核通過，納入合格供應商名單',
                'changed_by'  => $adminId,
                'created_at'  => now()->subMonths(6),
                'updated_at'  => now()->subMonths(6),
            ]);

            $count++;
        }

        $this->command->info("✓ supplier_status_histories 已植入（{$count} 筆）");
    }

    /** supplier_disclosures — 依 supplier_disclosure_fields 填入示範揭露值 */
    private function seedSupplierDisclosures(): void
    {
        $fields    = DB::table('supplier_disclosure_fields')->get(['slug', 'data_type']);
        $suppliers = DB::table('suppliers')->limit(6)->get(['id']);
        $count     = 0;

        foreach ($suppliers as $i => $s) {
            foreach ($fields as $f) {
                $exists = DB::table('supplier_disclosures')
                    ->where('supplier_id', $s->id)
                    ->where('field_slug', $f->slug)
                    ->where('period_year', 2024)
                    ->exists();
                if ($exists) continue;

                $row = [
                    'id'           => (string) Str::uuid(),
                    'supplier_id'  => $s->id,
                    'field_slug'   => $f->slug,
                    'period_year'  => 2024,
                    'source'       => 'saq_sync',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];

                if ($f->data_type === 'boolean') {
                    $row['boolean_value'] = ($i % 2 === 0);
                } else {
                    $row['numeric_value'] = round(100 + ($i * 37.5), 2);
                }

                DB::table('supplier_disclosures')->insert($row);
                $count++;
            }
        }

        $this->command->info("✓ supplier_disclosures 已植入（{$count} 筆）");
    }

    /** assessment_series_weights — 取系列範本的部分題目權重作為覆寫示範 */
    private function seedAssessmentSeriesWeights(): void
    {
        $seriesList = DB::table('assessment_series')->get(['id', 'template_id']);
        $count      = 0;

        foreach ($seriesList as $series) {
            $exists = DB::table('assessment_series_weights')->where('series_id', $series->id)->exists();
            if ($exists) continue;

            $bankQuestions = DB::table('saq_questions')
                ->where('template_id', $series->template_id)
                ->limit(5)
                ->get(['source_bank_question_id', 'weight']);

            foreach ($bankQuestions as $q) {
                if (!$q->source_bank_question_id) continue;

                DB::table('assessment_series_weights')->insert([
                    'id'                          => (string) Str::uuid(),
                    'series_id'                   => $series->id,
                    'source_template_question_id' => $q->source_bank_question_id,
                    'weight'                      => $q->weight,
                    'updated_at'                  => now(),
                ]);
                $count++;
            }
        }

        $this->command->info("✓ assessment_series_weights 已植入（{$count} 筆）");
    }

    /** saq_score_snapshots — 為已有分數的 SAQ 建立一筆送出時的快照 */
    private function seedSaqScoreSnapshots(): void
    {
        $saqs  = DB::table('saqs')->whereNotNull('score')->whereNotNull('grade')->get(['id', 'score', 'grade', 'submitted_at']);
        $count = 0;

        foreach ($saqs as $saq) {
            $exists = DB::table('saq_score_snapshots')->where('saq_id', $saq->id)->exists();
            if ($exists) continue;

            DB::table('saq_score_snapshots')->insert([
                'id'         => (string) Str::uuid(),
                'saq_id'     => $saq->id,
                'score'      => $saq->score,
                'grade'      => $saq->grade,
                'trigger'    => 'submit',
                'scored_at'  => $saq->submitted_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $count++;
        }

        $this->command->info("✓ saq_score_snapshots 已植入（{$count} 筆）");
    }

    /** saq_response_reviews — 為審核中/已完成 SAQ 的回覆建立審查者評分 */
    private function seedSaqResponseReviews(?string $reviewerId): void
    {
        $saqs = DB::table('saqs')
            ->whereIn('status', ['under_review', 'completed', 'review_returned'])
            ->limit(6)
            ->get(['id']);

        $count = 0;
        foreach ($saqs as $saq) {
            // saq_responses.raw_score 目前恆為 null（SaqResponseRefillSeeder 重填時未計算分數，
            // 須等實際審查動作觸發評分服務），故審查分數以確定性種子推算合理分數作為示範資料
            $responses = DB::table('saq_responses')
                ->where('saq_id', $saq->id)
                ->limit(5)
                ->get(['project_question_id']);

            foreach ($responses as $r) {
                $exists = DB::table('saq_response_reviews')
                    ->where('saq_id', $saq->id)
                    ->where('project_question_id', $r->project_question_id)
                    ->exists();
                if ($exists) continue;

                $seed  = crc32($saq->id . '_' . $r->project_question_id);
                $score = 60 + (abs($seed) % 36); // 60–95 分

                DB::table('saq_response_reviews')->insert([
                    'id'                   => (string) Str::uuid(),
                    'saq_id'               => $saq->id,
                    'project_question_id'  => $r->project_question_id,
                    'reviewer_id'          => $reviewerId,
                    'reviewer_score'       => $score,
                    'reason'               => null,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
                $count++;
            }
        }

        $this->command->info("✓ saq_response_reviews 已植入（{$count} 筆）");
    }

    /** permissions + role_has_permissions + model_has_permissions — 依 CLAUDE.md RBAC 矩陣 */
    private function seedPermissions(): void
    {
        $modules = ['dashboard', 'suppliers', 'saq', 'cap', 'reports', 'tradegoods', 'settings', 'portal'];
        $roleModules = [
            'admin'   => $modules,
            'buyer'   => ['dashboard', 'suppliers', 'tradegoods', 'cap'],
            'sustain' => ['dashboard', 'suppliers', 'saq', 'cap', 'reports'],
            'comply'  => ['dashboard', 'suppliers', 'saq', 'cap', 'tradegoods', 'reports'],
            'analyst' => ['dashboard', 'suppliers', 'saq', 'reports'],
            'supplier'   => ['portal'],
            'sup_esg'    => ['portal'],
        ];

        $permIds = [];
        foreach ($modules as $m) {
            $name = "view {$m}";
            $id   = DB::table('permissions')->where('name', $name)->where('guard_name', 'api')->value('id');
            if (!$id) {
                $id = DB::table('permissions')->insertGetId([
                    'name'       => $name,
                    'guard_name' => 'api',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $permIds[$m] = $id;
        }

        $roles = DB::table('roles')->where('guard_name', 'api')->pluck('id', 'name');

        foreach ($roleModules as $roleName => $mods) {
            $roleId = $roles->get($roleName);
            if (!$roleId) continue;

            foreach ($mods as $m) {
                $exists = DB::table('role_has_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permIds[$m])
                    ->exists();
                if (!$exists) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $permIds[$m],
                        'role_id'       => $roleId,
                    ]);
                }
            }
        }

        // model_has_permissions：admin 帳號額外直接授權一項權限（非僅透過角色）
        $adminUser = DB::table('users')->where('email', 'admin@esgchain.com')->first();
        if ($adminUser) {
            $exists = DB::table('model_has_permissions')
                ->where('model_id', $adminUser->id)
                ->where('permission_id', $permIds['settings'])
                ->exists();
            if (!$exists) {
                DB::table('model_has_permissions')->insert([
                    'permission_id' => $permIds['settings'],
                    'model_type'    => 'App\\Models\\User',
                    'model_id'      => $adminUser->id,
                ]);
            }
        }

        $this->command->info('✓ permissions + role_has_permissions + model_has_permissions 已植入');
    }

    /** saq_template_industries — 將部分範本標記適用之 SASB 產業 */
    private function seedSaqTemplateIndustries(): void
    {
        $templates  = DB::table('saq_templates')->whereNull('draft_of')->get(['id']);
        $industries = DB::table('sasb_industries')->limit(3)->pluck('id');
        $count      = 0;

        foreach ($templates as $t) {
            foreach ($industries as $industryId) {
                $exists = DB::table('saq_template_industries')
                    ->where('template_id', $t->id)
                    ->where('industry_id', $industryId)
                    ->exists();
                if (!$exists) {
                    DB::table('saq_template_industries')->insert([
                        'template_id' => $t->id,
                        'industry_id' => $industryId,
                    ]);
                    $count++;
                }
            }
        }

        $this->command->info("✓ saq_template_industries 已植入（{$count} 筆）");
    }

    /** supplier_imports — 一筆歷史 AVL CSV 匯入批次示範記錄 */
    private function seedSupplierImports(): void
    {
        if (DB::table('supplier_imports')->exists()) return;

        $batchId = (string) Str::uuid();
        DB::table('supplier_imports')->insert([
            [
                'id'              => (string) Str::uuid(),
                'batch_id'        => $batchId,
                'vendor_code'     => 'SUP-DEMO-001',
                'vendor_name'     => '台灣示範鋼鐵股份有限公司',
                'country_code'    => 'TW',
                'material_group'  => '鋼鐵原料',
                'cleanse_status'  => 'approved',
                'created_at'      => now()->subDays(10),
                'updated_at'      => now()->subDays(10),
            ],
            [
                'id'              => (string) Str::uuid(),
                'batch_id'        => $batchId,
                'vendor_code'     => 'SUP-DEMO-002',
                'vendor_name'     => '越南綠源環保科技有限公司',
                'country_code'    => 'VN',
                'material_group'  => '化學品',
                'cleanse_status'  => 'approved',
                'created_at'      => now()->subDays(10),
                'updated_at'      => now()->subDays(10),
            ],
        ]);

        $this->command->info('✓ supplier_imports 已植入（1 個批次，2 筆記錄）');
    }

    /**
     * Laravel 框架層內部表（cache/queue/session/password-reset）。
     * 這些表在本環境中實際未被讀取（QUEUE_CONNECTION=redis、SESSION_DRIVER=redis），
     * 純為滿足「所有資料表皆有資料」要求植入示範性資料，不影響任何運行邏輯。
     */
    private function seedFrameworkInfraTables(?string $adminId): void
    {
        if (!DB::table('cache')->exists()) {
            DB::table('cache')->insert([
                'key'        => 'demo:placeholder',
                'value'      => serialize('demo-value'),
                'expiration' => now()->addYear()->getTimestamp(),
            ]);
        }

        if (!DB::table('cache_locks')->exists()) {
            DB::table('cache_locks')->insert([
                'key'        => 'demo:placeholder-lock',
                'owner'      => 'seeder-demo',
                'expiration' => now()->addYear()->getTimestamp(),
            ]);
        }

        if (!DB::table('failed_jobs')->exists()) {
            DB::table('failed_jobs')->insert([
                'uuid'       => (string) Str::uuid(),
                'connection' => 'redis',
                'queue'      => 'default',
                'payload'    => json_encode(['displayName' => 'Demo\\PlaceholderJob']),
                'exception'  => "Demo placeholder failure (seeded for demo data completeness)\n#0 {main}",
                'failed_at'  => now()->subDays(5),
            ]);
        }

        if (!DB::table('job_batches')->exists()) {
            DB::table('job_batches')->insert([
                'id'             => (string) Str::uuid(),
                'name'           => 'demo-placeholder-batch',
                'total_jobs'     => 1,
                'pending_jobs'   => 0,
                'failed_jobs'    => 0,
                'failed_job_ids' => json_encode([]),
                'created_at'     => now()->subDays(5)->getTimestamp(),
                'finished_at'    => now()->subDays(5)->getTimestamp(),
            ]);
        }

        if (!DB::table('jobs')->exists()) {
            DB::table('jobs')->insert([
                'queue'         => 'default',
                'payload'       => json_encode(['displayName' => 'Demo\\PlaceholderJob']),
                'attempts'      => 0,
                'available_at'  => now()->getTimestamp(),
                'created_at'    => now()->getTimestamp(),
            ]);
        }

        if (!DB::table('password_reset_tokens')->exists()) {
            DB::table('password_reset_tokens')->insert([
                'email'      => 'admin@esgchain.com',
                'token'      => bcrypt(Str::random(40)),
                'created_at' => now()->subDays(30),
            ]);
        }

        if (!DB::table('sessions')->exists()) {
            // sessions.user_id 為標準 Laravel bigint 欄位，與本系統 UUID user id 不相容，留空
            DB::table('sessions')->insert([
                'id'            => Str::random(40),
                'user_id'       => null,
                'ip_address'    => '127.0.0.1',
                'user_agent'    => 'Demo Seeder',
                'payload'       => base64_encode(serialize([])),
                'last_activity' => now()->getTimestamp(),
            ]);
        }

        $this->command->info('✓ 框架層內部表（cache/jobs/sessions 等）已植入示範資料');
    }
}
