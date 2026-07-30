<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Scope3PushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $reportId) {}

    public function handle(): void
    {
        $aiUrl     = rtrim(config('services.ai.url', 'http://localhost:8000'), '/');
        $timeout   = (int) config('services.ai.timeout', 120);

        try {
            Http::withHeaders(['X-Internal-Token' => config('services.ai.internal_token')])
                ->timeout($timeout)->post("{$aiUrl}/ai/v1/celery/scope3-push", [
                'report_id' => $this->reportId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Scope3PushJob 呼叫 esgchain-ai 失敗', [
                'report_id' => $this->reportId,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
