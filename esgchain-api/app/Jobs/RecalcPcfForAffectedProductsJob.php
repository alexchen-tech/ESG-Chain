<?php

namespace App\Jobs;

use App\Models\ProductBomLine;
use App\Models\SalesProduct;
use App\Services\PCF\PcfCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RecalcPcfForAffectedProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        private readonly string $materialItemId,
        private readonly string $emissionId,
    ) {
        $this->delay(5);
    }

    public function handle(PcfCalculationService $pcfService): void
    {
        $lockKey = "recalc_pcf:{$this->materialItemId}";

        if (!Cache::add($lockKey, 1, 5)) {
            Log::info('RecalcPcfForAffectedProductsJob 去重跳過', [
                'material_item_id' => $this->materialItemId,
            ]);
            return;
        }

        try {
            $affectedProductIds = ProductBomLine::where('material_item_id', $this->materialItemId)
                ->distinct()
                ->pluck('sales_product_id');

            foreach ($affectedProductIds as $productId) {
                $product = SalesProduct::find($productId);
                if (!$product) continue;

                try {
                    $snapshot = $pcfService->snapshot($product);

                    Log::info('PCF 快照已更新', [
                        'sales_product_id' => $productId,
                        'snapshot_id'      => $snapshot->id,
                        'total_pcf'        => $snapshot->total_pcf,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('RecalcPcfForAffectedProductsJob 快照失敗', [
                        'sales_product_id' => $productId,
                        'error'            => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            Cache::forget($lockKey);
        }
    }
}
