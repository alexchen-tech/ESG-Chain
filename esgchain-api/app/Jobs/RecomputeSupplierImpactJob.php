<?php

namespace App\Jobs;

use App\Services\Risk\ImpactScoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * 非同步重算供應商 impact_score。
 *
 * 供 tier/spend 變動與 BOM 供應關係變動觸發使用，避免在批次 ERP sync 或
 * BOM 匯入的請求執行緒中同步呼叫 esgchain-ai 造成阻塞。
 */
class RecomputeSupplierImpactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly string $supplierId) {}

    public function handle(ImpactScoreService $service): void
    {
        $service->recomputeForSupplier($this->supplierId);
    }
}
