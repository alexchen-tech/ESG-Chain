<?php

namespace App\Console\Commands;

use App\Jobs\ComplianceDocExpiryJob;
use Illuminate\Console\Command;

class CheckComplianceExpiry extends Command
{
    protected $signature = 'compliance:check-expiry {--dry-run : 列出將觸發的文件清單，不實際建立 CAP}';
    protected $description = '掃描即將到期或已過期的合規文件，自動建立 CAP';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('[DRY RUN] 預覽模式 — 不會實際建立 CAP');
        }

        $job    = new ComplianceDocExpiryJob($dryRun);
        $result = $job->handle();

        foreach ($result['log'] as $line) {
            $this->line($line);
        }

        $action = $dryRun ? '將建立' : '已建立';
        $this->newLine();
        $this->info("完成：{$action} {$result['created']} 筆 CAP，跳過 {$result['skipped']} 筆（已有有效 CAP）");

        return Command::SUCCESS;
    }
}
