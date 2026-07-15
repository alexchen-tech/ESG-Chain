<?php

namespace App\Console\Commands;

use App\Models\RiskAssessment;
use App\Models\Supplier;
use App\Services\Risk\ImpactScoreService;
use Illuminate\Console\Command;

class BackfillImpactScores extends Command
{
    protected $signature = 'impact:backfill
        {--dry-run : 僅統計，不實際寫入}
        {--snapshot : 一併回填各供應商最新 RiskAssessment 的 impact_score 快照}';

    protected $description = '重算全體供應商 impact_score（四因子加權），可選回填最新風險評估快照';

    public function handle(ImpactScoreService $service): int
    {
        $suppliers = Supplier::all(['id', 'name']);
        $this->info("找到 {$suppliers->count()} 家供應商");

        $ok = $fail = 0;
        $dist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        foreach ($suppliers as $supplier) {
            if ($this->option('dry-run')) {
                $this->line("  [dry] {$supplier->name}");
                continue;
            }

            $score = $service->recomputeForSupplier($supplier->id);
            if ($score === null) {
                $fail++;
                $this->warn("  ✗ {$supplier->name}（AI 不可用或無資料）");
                continue;
            }

            $dist[$score]++;
            $ok++;

            if ($this->option('snapshot')) {
                $latest = RiskAssessment::where('supplier_id', $supplier->id)
                    ->orderByDesc('assessed_at')
                    ->first();
                if ($latest) {
                    RiskAssessment::where('id', $latest->id)->update(['impact_score' => $score]);
                }
            }
        }

        if (!$this->option('dry-run')) {
            $this->info("完成：成功 {$ok}、失敗 {$fail}");
            $this->line("Impact 分布 → 1:{$dist[1]}  2:{$dist[2]}  3:{$dist[3]}  4:{$dist[4]}  5:{$dist[5]}");
        }

        return self::SUCCESS;
    }
}
