<?php

namespace App\Jobs;

use App\Services\Erp\ErpSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ErpScheduledSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    /**
     * @param string[] $entities 要同步的實體類型，預設全部
     */
    public function __construct(
        private readonly array $entities = ['suppliers', 'materials', 'bom-lines', 'shipments'],
    ) {}

    public function handle(ErpSyncService $service): void
    {
        $since = Cache::get('erp_last_sync_at');

        Log::info('ErpScheduledSyncJob 開始', ['entities' => $this->entities, 'since' => $since]);

        $results = [];

        foreach ($this->entities as $entity) {
            try {
                $results[$entity] = match ($entity) {
                    'suppliers' => $service->syncSuppliers($since, 'scheduled'),
                    'materials' => $service->syncMaterials($since, 'scheduled'),
                    'bom-lines' => $service->syncBomLines($since, 'scheduled'),
                    'shipments' => $service->syncShipments($since, 'scheduled'),
                    default => ['skipped' => true],
                };
            } catch (\Throwable $e) {
                Log::error("ErpScheduledSyncJob {$entity} 同步失敗", ['error' => $e->getMessage()]);
                $results[$entity] = ['error' => $e->getMessage()];
            }
        }

        Cache::put('erp_last_sync_at', now()->toIso8601String(), now()->addDays(7));

        Log::info('ErpScheduledSyncJob 完成', ['results' => $results]);
    }
}
