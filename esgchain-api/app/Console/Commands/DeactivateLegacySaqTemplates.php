<?php

namespace App\Console\Commands;

use App\Models\SAQTemplate;
use Illuminate\Console\Command;

class DeactivateLegacySaqTemplates extends Command
{
    protected $signature   = 'saq:deactivate-legacy-templates {--dry-run : 僅顯示會被停用的範本，不實際執行}';
    protected $description = '停用非 multi-framework 的舊版 SAQ 範本（is_active = false）';

    public function handle(): int
    {
        $legacyFrameworks = ['ESG', 'ISO20400', 'ISO26000', 'esg', 'iso20400', 'iso26000'];

        $query = SAQTemplate::whereIn('scoring_framework', $legacyFrameworks)
            ->where('is_active', true)
            ->where('status', 'published');

        $templates = $query->get(['id', 'name', 'scoring_framework', 'version']);

        if ($templates->isEmpty()) {
            $this->info('無需停用的舊版範本。');
            return 0;
        }

        $this->table(
            ['ID', '名稱', '框架', '版本'],
            $templates->map(fn ($t) => [$t->id, $t->name, $t->scoring_framework, $t->version])
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry-run 模式：以上範本將被停用，但本次未實際執行。');
            return 0;
        }

        if (!$this->confirm("以上 {$templates->count()} 個舊版範本將被停用（is_active = false），確認執行？")) {
            $this->info('已取消。');
            return 0;
        }

        SAQTemplate::whereIn('id', $templates->pluck('id'))->update(['is_active' => false]);
        $this->info("✓ 已停用 {$templates->count()} 個舊版範本。");

        return 0;
    }
}
