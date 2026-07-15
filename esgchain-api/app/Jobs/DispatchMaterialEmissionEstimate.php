<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchMaterialEmissionEstimate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $materialItemId,
        private readonly string $supplierId,
        private readonly string $hsCode,
        private readonly ?string $materialName = null,
    ) {}

    public function handle(): void
    {
        $aiUrl = rtrim(config('services.ai.url', env('AI_SERVICE_URL', 'http://esgchain-ai:8000')), '/');

        try {
            Http::timeout(30)->post("{$aiUrl}/ai/v1/celery/material-emission-estimate", [
                'material_item_id' => $this->materialItemId,
                'supplier_id'      => $this->supplierId,
                'hs_code'          => $this->hsCode,
                'material_name'    => $this->materialName,
            ]);
        } catch (\Throwable $e) {
            Log::warning("物料碳排 AI 估算觸發失敗：{$e->getMessage()}", [
                'material_item_id' => $this->materialItemId,
                'supplier_id'      => $this->supplierId,
            ]);
        }
    }
}
