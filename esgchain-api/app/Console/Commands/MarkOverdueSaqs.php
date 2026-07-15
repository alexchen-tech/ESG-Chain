<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarkOverdueSaqs extends Command
{
    protected $signature   = 'saq:mark-overdue';
    protected $description = '標記逾期未完成的問卷（due_date 已過且狀態為 sent/in_progress）';

    public function handle(): int
    {
        // is_overdue 欄位需存在；若 migration 尚未執行則 graceful skip
        if (!DB::getSchemaBuilder()->hasColumn('saqs', 'is_overdue')) {
            $this->warn('saqs.is_overdue column not found, skipping.');
            return self::SUCCESS;
        }

        $now = Carbon::now();

        $overdueIds = DB::table('saqs')
            ->join('saq_projects', 'saqs.project_id', '=', 'saq_projects.id')
            ->whereIn('saqs.status', ['sent', 'in_progress'])
            ->where('saq_projects.due_date', '<', $now->toDateString())
            ->whereNull('saqs.deleted_at')
            ->pluck('saqs.id');

        if ($overdueIds->isEmpty()) {
            $this->info('No overdue SAQs found.');
            return self::SUCCESS;
        }

        DB::table('saqs')->whereIn('id', $overdueIds)->update([
            'is_overdue' => true,
            'updated_at' => $now,
        ]);

        Log::info('MarkOverdueSaqs: 標記逾期問卷', [
            'count'   => $overdueIds->count(),
            'saq_ids' => $overdueIds->take(10)->all(),
        ]);

        $this->info("Marked {$overdueIds->count()} SAQ(s) as overdue.");
        return self::SUCCESS;
    }
}
