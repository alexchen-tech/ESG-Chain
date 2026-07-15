<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPcfRecordToAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly array $payload) {}

    public function handle(): void
    {
        $aiUrl = rtrim(config('services.ai.url', env('AI_SERVICE_URL', 'http://esgchain-ai:8000')), '/');

        try {
            Http::timeout(60)->post("{$aiUrl}/ai/v1/pcf-records", $this->payload);
        } catch (\Throwable $e) {
            Log::error('SyncPcfRecordToAi 失敗', [
                'payload' => $this->payload,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
