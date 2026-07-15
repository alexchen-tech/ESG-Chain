<?php

namespace App\Jobs;

use App\Models\GeoEventSupplierReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class CheckRecalculatingTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        GeoEventSupplierReview::where('status', 'recalculating')
            ->where('recalculation_started_at', '<', Carbon::now()->subMinutes(10))
            ->update([
                'status'        => 'failed',
                'error_message' => '重算逾時（超過 10 分鐘無回應）',
            ]);
    }
}
