<?php

namespace App\Console\Commands;

use App\Services\PCF\PcfRequestService;
use Illuminate\Console\Command;

class UpdateOverduePcfRequests extends Command
{
    protected $signature   = 'pcf:update-overdue';
    protected $description = '將逾期（due_date < 今日且 status = pending）的 PCF 請求更新為 overdue';

    public function __construct(private PcfRequestService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->service->updateOverdue();
        $this->info("已更新 {$count} 筆 PCF 請求為逾期狀態");
        return Command::SUCCESS;
    }
}
