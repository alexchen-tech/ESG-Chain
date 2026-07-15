<?php

namespace App\Jobs;

use App\Services\PCF\PcfEmissionGapScanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PcfEmissionGapScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param string|null $salesProductId  per-product 掃描範圍（null = 全局）
     * @param string|null $supplierId      per-supplier 掃描範圍（null = 全供應商）
     * @param string      $triggerSource   觸發來源
     */
    public function __construct(
        private readonly ?string $salesProductId = null,
        private readonly ?string $supplierId = null,
        private readonly string $triggerSource = 'system_bom_import',
    ) {}

    public function handle(PcfEmissionGapScanService $service): void
    {
        Log::info('PcfEmissionGapScanJob 開始', [
            'sales_product_id' => $this->salesProductId,
            'supplier_id'      => $this->supplierId,
            'trigger_source'   => $this->triggerSource,
        ]);

        $result = $service->scan(
            salesProductId: $this->salesProductId,
            supplierId: $this->supplierId,
            triggerSource: $this->triggerSource,
        );

        Log::info('PcfEmissionGapScanJob 完成', $result);
    }
}
