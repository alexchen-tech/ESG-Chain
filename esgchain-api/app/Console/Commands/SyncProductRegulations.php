<?php

namespace App\Console\Commands;

use App\Services\Compliance\SupplierComplianceStatusService;
use Illuminate\Console\Command;

class SyncProductRegulations extends Command
{
    protected $signature   = 'sync:product-regulations';
    protected $description = '批量推算所有產品的適用法規（inferred_regulations）';

    public function handle(SupplierComplianceStatusService $service): int
    {
        $this->info('開始推算產品法規...');
        $count = $service->syncAllProductsInferredRegulations();
        $this->info("已更新 {$count} 筆產品法規");

        return Command::SUCCESS;
    }
}
