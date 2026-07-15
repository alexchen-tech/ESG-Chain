<?php

namespace App\Services\Dashboard;

use App\Models\CAP;
use App\Models\SAQ;
use App\Models\Supplier;
use App\Models\SupplierComplianceDoc;
use App\Models\SupplierStatusHistory;
use App\Models\SystemSetting;
use App\Models\TradeGood;
use App\Models\User;
use App\Services\Risk\SixDimRiskThresholds;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getSummary(User $user): array
    {
        $now  = Carbon::now();
        $in7d = Carbon::now()->addDays(7);

        // 各角色共用數字
        $saqPending     = SAQ::where('status', 'submitted')->count();
        $capDue7d       = CAP::whereIn('status', ['open', 'in_progress'])
                              ->whereBetween('due_date', [$now, $in7d])
                              ->count();
        $docExpiring7d  = SupplierComplianceDoc::whereBetween('expires_at', [$now, $in7d])->count();
        // 高風險：六維任一合規分低於對應閾值（dim_eN < threshold = 高風險）
        [$highRiskIds] = $this->calcHighRiskSuppliers();
        $highRisk = Supplier::whereNotIn('status', ['terminated'])->whereIn('id', $highRiskIds)->count();
        $pendingReview  = Supplier::where('status', 'pending_review')->count();
        // expired = expires_at 已過期；no missing column, use expired count
        $complianceIssues = SupplierComplianceDoc::where('expires_at', '<', $now)
                              ->distinct('supplier_id')->count('supplier_id');
        $cbamProducts   = TradeGood::where('is_cbam_applicable', true)->count();
        $eudrPending    = TradeGood::where('is_eudr_applicable', true)->count();

        $role = $user->roles()->first()?->name ?? 'buyer';

        return match ($role) {
            'sustain' => [
                'cards' => [
                    ['key' => 'saq_pending',      'label' => 'SAQ 待審核',       'value' => $saqPending,    'link' => '/questionnaires', 'urgent' => $saqPending > 0],
                    ['key' => 'cap_due_7d',        'label' => 'CAP 7 天內到期',   'value' => $capDue7d,      'link' => '/cap',             'urgent' => $capDue7d > 0],
                    ['key' => 'doc_expiring_7d',   'label' => '合規文件即將到期', 'value' => $docExpiring7d, 'link' => '/suppliers',       'urgent' => $docExpiring7d > 0],
                ],
            ],
            'buyer' => [
                'cards' => [
                    ['key' => 'doc_expiring_7d',  'label' => '文件 7 天內到期',  'value' => $docExpiring7d, 'link' => '/suppliers',  'urgent' => $docExpiring7d > 0],
                    ['key' => 'high_risk',         'label' => '高風險供應商',     'value' => $highRisk,      'link' => '/risk',       'urgent' => $highRisk > 0],
                    ['key' => 'pending_review',    'label' => '待審核供應商',     'value' => $pendingReview, 'link' => '/suppliers',  'urgent' => $pendingReview > 0],
                ],
            ],
            'analyst' => [
                'cards' => [
                    ['key' => 'saq_pending', 'label' => 'SAQ 待審核',    'value' => $saqPending, 'link' => '/questionnaires', 'urgent' => $saqPending > 0],
                    ['key' => 'high_risk',   'label' => '高風險供應商', 'value' => $highRisk,    'link' => '/risk',           'urgent' => $highRisk > 0],
                ],
            ],
            'comply' => [
                'cards' => [
                    ['key' => 'compliance_issues', 'label' => '商品合規問題',    'value' => $complianceIssues, 'link' => '/trade-goods', 'urgent' => $complianceIssues > 0],
                    ['key' => 'cbam_products',     'label' => 'CBAM 申報商品',   'value' => $cbamProducts,     'link' => '/trade-goods', 'urgent' => false],
                    ['key' => 'eudr_pending',      'label' => 'EUDR 未提交',     'value' => $eudrPending,      'link' => '/trade-goods', 'urgent' => $eudrPending > 0],
                ],
            ],
            default => [ // admin
                'cards' => [
                    ['key' => 'saq_pending',       'label' => 'SAQ 待審核',      'value' => $saqPending,       'link' => '/questionnaires', 'urgent' => $saqPending > 0],
                    ['key' => 'cap_due_7d',         'label' => 'CAP 7 天內到期', 'value' => $capDue7d,         'link' => '/cap',            'urgent' => $capDue7d > 0],
                    ['key' => 'doc_expiring_7d',    'label' => '文件即將到期',   'value' => $docExpiring7d,    'link' => '/suppliers',      'urgent' => $docExpiring7d > 0],
                    ['key' => 'high_risk',          'label' => '高風險供應商',   'value' => $highRisk,         'link' => '/risk',           'urgent' => $highRisk > 0],
                    ['key' => 'compliance_issues',  'label' => '商品合規問題',   'value' => $complianceIssues, 'link' => '/trade-goods',    'urgent' => $complianceIssues > 0],
                    ['key' => 'cbam_products',      'label' => 'CBAM 商品',      'value' => $cbamProducts,     'link' => '/trade-goods',    'urgent' => false],
                ],
            ],
        };
    }

    public function getRecentActivity(int $days = 7, int $limit = 20): array
    {
        $since = Carbon::now()->subDays($days);
        $events = collect();

        // 供應商狀態變更
        $statusChanges = SupplierStatusHistory::where('created_at', '>=', $since)
            ->with('supplier:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($h) => [
                'supplier_id'   => $h->supplier_id,
                'supplier_name' => $h->supplier?->name ?? '未知供應商',
                'event_type'    => 'status_change',
                'event_label'   => "狀態變更：{$h->from_status} → {$h->to_status}",
                'occurred_at'   => $h->created_at->toIso8601String(),
                'severity'      => 'info',
            ]);
        $events = $events->concat($statusChanges);

        // SAQ 提交
        $saqSubmits = SAQ::where('submitted_at', '>=', $since)
            ->with('supplier:id,name')
            ->orderByDesc('submitted_at')
            ->get()
            ->map(fn($s) => [
                'supplier_id'   => $s->supplier_id,
                'supplier_name' => $s->supplier?->name ?? '未知供應商',
                'event_type'    => 'saq_submitted',
                'event_label'   => 'SAQ 問卷已提交，等待審核',
                'occurred_at'   => $s->submitted_at->toIso8601String(),
                'severity'      => 'pending',
            ]);
        $events = $events->concat($saqSubmits);

        // CAP 更新（supplier_id 可能因自動建立而指向不存在的 supplier，從 title 萃取供應商名稱作為 fallback）
        $capUpdates = CAP::where('updated_at', '>=', $since)
            ->with('supplier:id,name')
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get()
            ->map(function ($c) {
                $supplierName = $c->supplier?->name;
                if (!$supplierName) {
                    // title 格式：[DOC_TYPE] 文字 — 供應商名稱
                    preg_match('/—\s*(.+)$/', $c->title, $m);
                    $supplierName = $m[1] ?? '未知供應商';
                }
                return [
                    'supplier_id'   => $c->supplier_id,
                    'supplier_name' => $supplierName,
                    'event_type'    => 'cap_updated',
                    'event_label'   => "CAP「{$c->title}」狀態：{$c->status}",
                    'occurred_at'   => $c->updated_at->toIso8601String(),
                    'severity'      => $c->status === 'overdue' ? 'warning' : 'info',
                ];
            });
        $events = $events->concat($capUpdates);

        // 合規文件 7 天內到期
        $expiringDocs = SupplierComplianceDoc::whereBetween('expires_at', [Carbon::now(), Carbon::now()->addDays(7)])
            ->with('supplier:id,name')
            ->orderBy('expires_at')
            ->get()
            ->map(fn($d) => [
                'supplier_id'   => $d->supplier_id,
                'supplier_name' => $d->supplier?->name ?? '未知供應商',
                'event_type'    => 'doc_expiring',
                'event_label'   => "{$d->doc_type} 即將到期（" . (int) ceil(max(0, Carbon::now()->diffInDays($d->expires_at, false))) . " 天後）",
                'occurred_at'   => $d->expires_at->toIso8601String(),
                'severity'      => 'warning',
            ]);
        $events = $events->concat($expiringDocs);

        // 合規文件新上傳
        $newDocs = SupplierComplianceDoc::where('created_at', '>=', $since)
            ->with('supplier:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($d) => [
                'supplier_id'   => $d->supplier_id,
                'supplier_name' => $d->supplier?->name ?? '未知供應商',
                'event_type'    => 'doc_uploaded',
                'event_label'   => "上傳合規文件：{$d->doc_type}",
                'occurred_at'   => $d->created_at->toIso8601String(),
                'severity'      => 'info',
            ]);
        $events = $events->concat($newDocs);

        return $events->sortByDesc('occurred_at')->take($limit)->values()->all();
    }

    public function getExpiringDocs(): array
    {
        $now  = Carbon::now();
        $in7d = Carbon::now()->addDays(7);

        return SupplierComplianceDoc::whereBetween('expires_at', [$now, $in7d])
            ->with('supplier:id,name')
            ->orderBy('expires_at')
            ->get()
            ->map(fn($d) => [
                'supplier_id'   => $d->supplier_id,
                'supplier_name' => $d->supplier?->name ?? '未知供應商',
                'doc_type'      => $d->doc_type,
                'expires_at'    => $d->expires_at->toDateString(),
                'days_remaining' => (int) ceil(max(0, $now->diffInDays($d->expires_at, false))),
            ])
            ->all();
    }

    public function getComplianceRisk(): array
    {
        $carbonPrice = (float) SystemSetting::get('carbon_price_eur', '65.00');

        $cbamGoods = TradeGood::where('is_cbam_applicable', true)->get();

        $totalCbamEmissions = 0.0;
        $totalRiskAmount    = 0.0;
        $missingEmissions   = 0;

        foreach ($cbamGoods as $good) {
            if ($good->embedded_emissions !== null) {
                $totalCbamEmissions += (float) $good->embedded_emissions;
                $totalRiskAmount    += (float) $good->embedded_emissions * $carbonPrice;
            } else {
                $missingEmissions++;
            }
        }

        $complianceIssues = SupplierComplianceDoc::where('expires_at', '<', Carbon::now())
            ->distinct('supplier_id')->count('supplier_id');
        $eudrPending      = TradeGood::where('is_eudr_applicable', true)->count();

        return [
            'carbon_price_eur'        => $carbonPrice,
            'cbam_products_count'     => $cbamGoods->count(),
            'cbam_total_emissions'    => round($totalCbamEmissions, 2),
            'cbam_risk_amount_eur'    => round($totalRiskAmount, 2),
            'cbam_missing_emissions'  => $missingEmissions,
            'compliance_issues_count' => $complianceIssues,
            'eudr_pending_count'      => $eudrPending,
        ];
    }

    public function getEsgScores(): array
    {
        // 每個供應商取最新一筆 RA，彙總三軸平均分數與 level 分布
        $rows = DB::table('risk_assessments as ra')
            ->joinSub(
                DB::table('risk_assessments')
                    ->select('supplier_id', DB::raw('MAX(assessed_at) as latest'))
                    ->whereNotNull('axis1_score')
                    ->groupBy('supplier_id'),
                'latest',
                fn($j) => $j->on('ra.supplier_id', '=', 'latest.supplier_id')
                             ->on('ra.assessed_at', '=', 'latest.latest')
            )
            ->select('ra.axis1_score', 'ra.axis2_score', 'ra.axis3_score')
            ->get();

        if ($rows->isEmpty()) {
            return ['axis1' => null, 'axis2' => null, 'axis3' => null, 'supplier_count' => 0];
        }

        $toLevel = function (?float $score): ?string {
            if ($score === null) return null;
            return match (true) {
                $score >= 80 => 'extreme',
                $score >= 60 => 'high',
                $score >= 40 => 'medium',
                $score >= 20 => 'low',
                default      => 'very_low',
            };
        };

        $buildAxis = function (string $field) use ($rows, $toLevel): array {
            $scores  = $rows->pluck($field)->filter(fn($v) => $v !== null);
            $avg     = $scores->isNotEmpty() ? round($scores->avg(), 1) : null;
            $dist    = ['extreme' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'very_low' => 0];
            foreach ($scores as $s) {
                $level = $toLevel((float) $s);
                if ($level) $dist[$level]++;
            }
            return ['avg_score' => $avg, 'level' => $toLevel($avg), 'distribution' => $dist];
        };

        return [
            'axis1'          => $buildAxis('axis1_score'),
            'axis2'          => $buildAxis('axis2_score'),
            'axis3'          => $buildAxis('axis3_score'),
            'supplier_count' => $rows->count(),
        ];
    }

    /**
     * 以六維閾值判斷高風險供應商。
     *
     * @return array{0: string[], 1: array<string,int>}
     *   [高風險供應商 ID 陣列, 各維度觸發高風險的供應商數量]
     */
    public function calcHighRiskSuppliers(): array
    {
        $thresholds = SixDimRiskThresholds::THRESHOLDS;

        // 每個供應商取最新一筆 RA（有任一六維分數者）
        $rows = DB::table('risk_assessments as ra')
            ->joinSub(
                DB::table('risk_assessments')
                    ->select('supplier_id', DB::raw('MAX(assessed_at) as latest'))
                    ->where(function ($q) {
                        $q->whereNotNull('dim_e1')->orWhereNotNull('dim_e2')
                          ->orWhereNotNull('dim_e3')->orWhereNotNull('dim_e4')
                          ->orWhereNotNull('dim_e5');
                    })
                    ->groupBy('supplier_id'),
                'latest',
                fn ($j) => $j->on('ra.supplier_id', '=', 'latest.supplier_id')
                              ->on('ra.assessed_at', '=', 'latest.latest')
            )
            ->select('ra.supplier_id', 'ra.dim_e1', 'ra.dim_e2', 'ra.dim_e3', 'ra.dim_e4', 'ra.dim_e5', 'ra.dim_e6')
            ->get();

        $highRiskIds = [];
        $breakdown   = array_fill_keys(array_keys($thresholds), 0);

        foreach ($rows as $row) {
            $dims = [
                'dim_e1' => $row->dim_e1,
                'dim_e2' => $row->dim_e2,
                'dim_e3' => $row->dim_e3,
                'dim_e4' => $row->dim_e4,
                'dim_e5' => $row->dim_e5,
                'dim_e6' => $row->dim_e6,
            ];
            // E6 無法從 RA 取得 regulations，保守略過（法規合規由 MarketComplianceChecker 處理）
            $riskDims = SixDimRiskThresholds::getRiskDims($dims, []);

            if (count($riskDims) > 0) {
                $highRiskIds[] = $row->supplier_id;
                foreach ($riskDims as $dim) {
                    $breakdown[$dim]++;
                }
            }
        }

        return [array_unique($highRiskIds), $breakdown];
    }

    /**
     * 高風險 KPI 含六維 breakdown。
     */
    public function getHighRiskSummary(): array
    {
        [$ids, $breakdown] = $this->calcHighRiskSuppliers();
        $count = Supplier::whereNotIn('status', ['terminated'])->whereIn('id', $ids)->count();

        return [
            'high_risk_count'          => $count,
            'high_risk_dims_breakdown' => $breakdown,
        ];
    }
}
