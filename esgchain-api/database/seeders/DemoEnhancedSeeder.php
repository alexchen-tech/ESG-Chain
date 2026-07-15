<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 增加 DEMO 資料量以利測試
 * 新增：20 家供應商、各 2–3 筆歷史 SAQ（含分數）、對應 RiskAssessment（含 source_saq_id）、CAP
 */
class DemoEnhancedSeeder extends Seeder
{
    private string $projectId2024;
    private string $projectId2025;
    private string $templateId;
    private array $reviewerIds = [];

    public function run(): void
    {
        $this->projectId2024 = DB::table('saq_projects')->where('name', 'like', '%2024%')->value('id') ?? DB::table('saq_projects')->value('id');
        $this->projectId2025 = DB::table('saq_projects')->where('name', 'like', '%2025%')->value('id') ?? $this->projectId2024;
        $this->templateId    = DB::table('saq_templates')->where('status', 'published')->value('id');
        $this->reviewerIds   = DB::table('users')->pluck('id')->toArray();

        $suppliers = $this->makeSuppliers();
        foreach ($suppliers as $sup) {
            $this->makeSupplierHistory($sup);
        }

        $this->command->info('DemoEnhancedSeeder: 完成，新增 ' . count($suppliers) . ' 家供應商');
    }

    // ─── 供應商定義 ────────────────────────────────────────────────────────────

    private function makeSuppliers(): array
    {
        $definitions = [
            // 越南紡織成衣供應商
            ['name' => 'Viet Long Garment Co., Ltd.',   'code' => 'VLG-001', 'country' => 'VN', 'industry' => '成衣', 'tier' => 1, 'stage' => 'reviewing',  'risk' => 'high',   'spend' => 4400000],
            // 中國大陸紡織供應商（已移除非紡織相關）
            // 印度紡織供應商
            ['name' => 'Mumbai Textile Mills Pvt. Ltd.', 'code' => 'MTM-001', 'country' => 'IN', 'industry' => '紡織品', 'tier' => 1, 'stage' => 'reviewing',  'risk' => 'high',   'spend' => 5200000],
            // 印尼供應商（成衣/包材）
            ['name' => 'PT. Jakarta Packaging Solutions','code' => 'JPS-001', 'country' => 'ID', 'industry' => '包裝材料', 'tier' => 3, 'stage' => 'invited',    'risk' => 'high',   'spend' => 640000],
            // 孟加拉供應商
            ['name' => 'Dhaka Garment Factory Ltd.',    'code' => 'DGF-001', 'country' => 'BD', 'industry' => '成衣', 'tier' => 1, 'stage' => 'reviewing',  'risk' => 'extreme','spend' => 3900000],
        ];

        $groupId = DB::table('supplier_groups')->value('id');
        $inserted = [];
        $now = now()->toDateTimeString();

        foreach ($definitions as $def) {
            // Skip if code already exists
            if (DB::table('suppliers')->where('code', $def['code'])->exists()) {
                $id = DB::table('suppliers')->where('code', $def['code'])->value('id');
                $inserted[] = array_merge($def, ['id' => $id]);
                continue;
            }

            $id = (string) Str::uuid();
            $riskScore = match ($def['risk']) {
                'low'     => round(rand(5, 25) / 100, 2),
                'medium'  => round(rand(30, 55) / 100, 2),
                'high'    => round(rand(60, 79) / 100, 2),
                'extreme' => round(rand(80, 98) / 100, 2),
                default   => 0.3,
            };

            DB::table('suppliers')->insert([
                'id'               => $id,
                'group_id'         => $groupId,
                'name'             => $def['name'],
                'code'             => $def['code'],
                'country_code'     => $def['country'],
                'industry'         => $def['industry'],
                'tier'             => $def['tier'],
                'status'           => 'active',
                'onboarding_stage' => $def['stage'],
                'risk_score'       => $riskScore,
                'spend_amount'     => $def['spend'],
                'profile_completed'=> 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            // 新增主要聯絡人
            DB::table('supplier_contacts')->insert([
                'id'          => (string) Str::uuid(),
                'supplier_id' => $id,
                'name'        => $this->fakeName($def['country']),
                'title'       => 'ESG Manager',
                'email'       => strtolower(str_replace([' ', '.', ','], '', $def['code'])) . '@supplier.demo',
                'phone'       => '+886-2-' . rand(2000, 9999) . '-' . rand(1000, 9999),
                'is_primary'  => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $inserted[] = array_merge($def, ['id' => $id]);
        }

        return $inserted;
    }

    // ─── 歷史 SAQ + RA + CAP ─────────────────────────────────────────────────

    private function makeSupplierHistory(array $sup): void
    {
        $supplierId = $sup['id'];
        $risk       = $sup['risk'];
        $now        = now();

        // 跳過已有足夠歷史的供應商
        $existingRaCount = DB::table('risk_assessments')->where('supplier_id', $supplierId)->count();
        if ($existingRaCount >= 3) return;

        // 建立 2–3 期歷史 SAQ（scored，各相差 6 個月）
        $periods = [
            ['offset_months' => -18, 'project_id' => $this->projectId2024],
            ['offset_months' => -12, 'project_id' => $this->projectId2024],
            ['offset_months' => -6,  'project_id' => $this->projectId2025],
        ];

        // 低風險供應商 2 期，高風險 3 期（更多歷史）
        if (in_array($risk, ['low', 'medium'])) {
            $periods = array_slice($periods, 1);
        }

        $prevSaqId = null;
        foreach ($periods as $p) {
            // 若同 project+supplier+template 已存在，跳過
            if (DB::table('saqs')->where('project_id', $p['project_id'])
                    ->where('supplier_id', $supplierId)
                    ->where('template_id', $this->templateId)->exists()) {
                continue;
            }

            $sentAt      = $now->copy()->addMonths($p['offset_months'])->subDays(30);
            $submittedAt = $sentAt->copy()->addDays(rand(14, 28));
            $reviewedAt  = $submittedAt->copy()->addDays(rand(7, 14));

            [$score, $grade] = $this->scoreForRisk($risk, rand(0, 15));
            $scoreE = round($score + rand(-8, 8), 1);
            $scoreS = round($score + rand(-8, 8), 1);
            $scoreG = round($score + rand(-8, 8), 1);
            foreach (['scoreE', 'scoreS', 'scoreG'] as $k) $$k = max(0, min(100, $$k));

            $saqId = (string) Str::uuid();
            DB::table('saqs')->insert([
                'id'                => $saqId,
                'project_id'        => $p['project_id'],
                'supplier_id'       => $supplierId,
                'template_id'       => $this->templateId,
                'status'            => 'completed',
                'score'             => $score,
                'score_e'           => $scoreE,
                'score_s'           => $scoreS,
                'score_g'           => $scoreG,
                'grade'             => $grade,
                'sent_at'           => $sentAt,
                'submitted_at'      => $submittedAt,
                'reviewed_by_id'    => $this->reviewerIds[array_rand($this->reviewerIds)] ?? null,
                'reviewed_at'       => $reviewedAt,
                'created_at'        => $sentAt,
                'updated_at'        => $reviewedAt,
            ]);

            // 建立對應的 RiskAssessment（source_saq_id 連結）
            [$probs, $impacts] = $this->dimScoresForRisk($risk);
            $raId = (string) Str::uuid();
            DB::table('risk_assessments')->insert([
                'id'             => $raId,
                'supplier_id'    => $supplierId,
                'e_probability'  => $probs[0],
                'e_impact'       => $impacts[0],
                's_probability'  => $probs[1],
                's_impact'       => $impacts[1],
                'g_probability'  => $probs[2],
                'g_impact'       => $impacts[2],
                'gp_probability' => $probs[3],
                'gp_impact'      => $impacts[3],
                'assessed_at'    => $reviewedAt,
                'assessed_by'    => $this->reviewerIds[array_rand($this->reviewerIds)] ?? null,
                'notes'          => '自動從 SAQ 評分產生',
                'source_saq_id'  => $saqId,
                'created_at'     => $reviewedAt,
                'updated_at'     => $reviewedAt,
            ]);

            // extreme / high → 建立 CAP
            if (in_array($risk, ['extreme', 'high'])) {
                $dimLabel = $this->worstDimLabel($probs, $impacts);
                DB::table('caps')->insert([
                    'id'          => (string) Str::uuid(),
                    'supplier_id' => $supplierId,
                    'saq_id'      => $saqId,
                    'source_type' => 'risk_assessment',
                    'source_id'   => $raId,
                    'title'       => "風險評估警示：{$sup['name']} — {$dimLabel} 維度需改善",
                    'description' => "{$dimLabel} 維度評分達 " . ($risk === 'extreme' ? 'Extreme' : 'High') . " 等級，需立即採取矯正行動",
                    'status'      => rand(0, 1) ? 'open' : 'in_progress',
                    'priority'    => $risk === 'extreme' ? 'critical' : 'high',
                    'due_date'    => $reviewedAt->copy()->addDays(90)->toDateString(),
                    'assigned_to' => null,
                    'created_by'  => null,
                    'created_at'  => $reviewedAt,
                    'updated_at'  => $reviewedAt,
                ]);
            }

            $prevSaqId = $saqId;
        }

        // 若供應商仍在 reviewing 且最新 SAQ 是 completed → 加一筆 pending submitted SAQ
        if ($sup['stage'] === 'reviewing' && in_array($risk, ['high', 'extreme', 'medium'])
            && !DB::table('saqs')->where('project_id', $this->projectId2025)
                ->where('supplier_id', $supplierId)->where('template_id', $this->templateId)
                ->where('status', 'submitted')->exists()) {
            $sentAt      = $now->copy()->subDays(rand(20, 40));
            $submittedAt = $sentAt->copy()->addDays(rand(5, 15));
            DB::table('saqs')->insert([
                'id'          => (string) Str::uuid(),
                'project_id'  => $this->projectId2025,
                'supplier_id' => $supplierId,
                'template_id' => $this->templateId,
                'status'      => 'submitted',
                'score'       => null,
                'grade'       => null,
                'sent_at'     => $sentAt,
                'submitted_at'=> $submittedAt,
                'created_at'  => $sentAt,
                'updated_at'  => $submittedAt,
            ]);
        }
    }

    // ─── 輔助方法 ──────────────────────────────────────────────────────────────

    private function scoreForRisk(string $risk, int $jitter): array
    {
        $base = match ($risk) {
            'low'     => rand(78, 92),
            'medium'  => rand(58, 74),
            'high'    => rand(38, 56),
            'extreme' => rand(18, 36),
            default   => 60,
        };
        $score = max(0, min(100, $base + $jitter - 7));
        $grade = match (true) {
            $score >= 85 => 'A',
            $score >= 70 => 'B',
            $score >= 55 => 'C',
            $score >= 40 => 'D',
            default      => 'E',
        };
        return [$score, $grade];
    }

    private function dimScoresForRisk(string $risk): array
    {
        // [E, S, G, GP] probability & impact 1–5
        $map = [
            'low'     => [[1,1,1,1], [2,2,2,1]],
            'medium'  => [[2,2,2,2], [3,3,3,3]],
            'high'    => [[3,3,4,3], [4,4,4,4]],
            'extreme' => [[4,5,4,4], [5,5,4,5]],
        ];
        [$probs, $impacts] = $map[$risk] ?? $map['medium'];
        // 加一點隨機擾動
        foreach ($probs as &$v) $v = max(1, min(5, $v + rand(-1, 1)));
        foreach ($impacts as &$v) $v = max(1, min(5, $v + rand(-1, 1)));
        return [$probs, $impacts];
    }

    private function worstDimLabel(array $probs, array $impacts): string
    {
        $dims = ['E', 'S', 'G', 'GP'];
        $scores = array_map(fn($i) => $probs[$i] * $impacts[$i], range(0, 3));
        $maxIdx = array_keys($scores, max($scores))[0];
        return $dims[$maxIdx];
    }

    private function fakeName(string $country): string
    {
        $names = [
            'TW' => ['陳俊宏', '林佳慧', '黃建志', '張雅琪', '李冠宇'],
            'VN' => ['Nguyen Van An', 'Tran Thi Bich', 'Le Quang Huy'],
            'CN' => ['王建国', '李梅', '张伟', '刘静', '陈龙'],
            'TH' => ['Somchai Nakorn', 'Nattaya Prom', 'Wichai Lek'],
            'IN' => ['Rajesh Kumar', 'Priya Singh', 'Amit Sharma'],
            'ID' => ['Budi Santoso', 'Sari Dewi', 'Ahmad Rizal'],
            'MY' => ['Ahmad Fadzil', 'Tan Wei Lim', 'Ravi Kumar'],
            'KR' => ['김민준', '이서연', '박지훈'],
            'BD' => ['Mohammed Alam', 'Fatema Begum', 'Karim Rahman'],
        ];
        $pool = $names[$country] ?? ['John Smith'];
        return $pool[array_rand($pool)];
    }
}
