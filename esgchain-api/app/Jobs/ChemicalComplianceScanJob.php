<?php

namespace App\Jobs;

use App\Services\Chemical\ChemicalComplianceScanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ChemicalComplianceScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $materialItemId) {}

    public function handle(ChemicalComplianceScanService $service): void
    {
        $alerts = $service->scanMaterialItem($this->materialItemId);
        Log::info('ChemicalComplianceScanJob 完成', [
            'material_item_id' => $this->materialItemId,
            'new_alerts'       => count($alerts),
        ]);
    }
}
