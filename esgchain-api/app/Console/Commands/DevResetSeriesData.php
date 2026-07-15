<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DevResetSeriesData extends Command
{
    protected $signature = 'dev:reset-series-data {--force : 跳過確認提示}';
    protected $description = '（Dev only）清除所有系列、專案、評核與關聯資料，準備重新建立 Seed';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('此指令不可在 Production 環境執行。');
            return 1;
        }

        if (!$this->option('force')) {
            if (!$this->confirm('即將清除 assessment_series, saq_projects, saqs, saq_responses, supplier_disclosures, cap_findings, caps。確定繼續？')) {
                $this->info('已取消。');
                return 0;
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $tables = [
            'cap_findings',
            'caps',
            'supplier_disclosures',
            'saq_responses',
            'saq_score_snapshots',
            'saq_response_reviews',
            'saqs',
            'project_questions',
            'saq_projects',
            'assessment_series_weights',
            'assessment_series',
        ];

        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            DB::table($table)->delete();
            $this->info("清除 {$table}：{$count} 筆");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('清除完成，可執行 php artisan db:seed 重建資料。');
        return 0;
    }
}
