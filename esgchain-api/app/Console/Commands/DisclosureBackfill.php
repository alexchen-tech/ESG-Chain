<?php

namespace App\Console\Commands;

use App\Models\SAQ;
use App\Services\Disclosure\DisclosureSyncService;
use Illuminate\Console\Command;

class DisclosureBackfill extends Command
{
    protected $signature = 'disclosure:backfill {--dry-run : 僅列出，不實際寫入}';
    protected $description = '對所有已評分的 SAQ 補跑 disclosure sync';

    public function handle(DisclosureSyncService $syncService): int
    {
        $saqs = SAQ::whereNotNull('score')
            ->whereNotIn('status', ['draft', 'sent'])
            ->get();

        $this->info("找到 {$saqs->count()} 份已評分 SAQ");

        $ok = $fail = 0;
        foreach ($saqs as $saq) {
            if ($this->option('dry-run')) {
                $this->line("  [dry] {$saq->id}");
                continue;
            }
            try {
                $syncService->syncFromSaq($saq);
                $this->line("  ✓ {$saq->id}");
                $ok++;
            } catch (\Throwable $e) {
                $this->error("  ✗ {$saq->id}: {$e->getMessage()}");
                $fail++;
            }
        }

        $this->info("完成：{$ok} ok, {$fail} failed");
        return Command::SUCCESS;
    }
}
