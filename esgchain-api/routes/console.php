<?php

use App\Console\Commands\CheckComplianceExpiry;
use App\Console\Commands\MarkOverdueSaqs;
use App\Console\Commands\SyncProductRegulations;
use App\Console\Commands\UpdateOverduePcfRequests;
use App\Jobs\CheckRecalculatingTimeoutJob;
use App\Jobs\ErpScheduledSyncJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 每日 UTC 01:00 掃描合規文件到期，自動建立 CAP
Schedule::command(CheckComplianceExpiry::class)->dailyAt('01:00');

// 每日 UTC 02:00 批量推算所有產品適用法規
Schedule::command(SyncProductRegulations::class)->dailyAt('02:00');

// 每日 UTC 00:30 將逾期 PCF 請求更新為 overdue
Schedule::command(UpdateOverduePcfRequests::class)->dailyAt('00:30');

// 每日 UTC 16:05（台灣時間 00:05）標記逾期未完成的問卷
Schedule::command(MarkOverdueSaqs::class)->dailyAt('16:05');

// 每小時增量同步 ERP 資料（供應商/物料/BOM/出貨）
Schedule::job(new ErpScheduledSyncJob())->hourly()->withoutOverlapping(10);

// 每 5 分鐘檢查地緣事件 E4 重算逾時
Schedule::job(new CheckRecalculatingTimeoutJob())->everyFiveMinutes();
