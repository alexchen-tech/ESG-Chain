<?php

namespace App\Jobs;

use App\Models\CAP;
use App\Models\CAPFinding;
use App\Models\SupplierComplianceDoc;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ComplianceDocExpiryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $dryRun;
    public array $log = [];

    public function __construct(bool $dryRun = false)
    {
        $this->dryRun = $dryRun;
    }

    public function handle(): array
    {
        $today    = Carbon::now()->startOfDay();
        $window   = $today->copy()->addDays(30);
        $created  = 0;
        $skipped  = 0;

        SupplierComplianceDoc::with('supplier')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $window)
            ->orderBy('expires_at')
            ->chunk(100, function ($docs) use ($today, &$created, &$skipped) {
                foreach ($docs as $doc) {
                    $hasActiveCap = CAP::where('source_id', $doc->id)
                        ->where('source_type', 'compliance_doc')
                        ->whereIn('status', ['open', 'in_progress'])
                        ->exists();

                    if ($hasActiveCap) {
                        $skipped++;
                        $this->log[] = "SKIP  [{$doc->supplier->name}] {$doc->doc_type} — CAP already open";
                        continue;
                    }

                    $daysLeft = $today->diffInDays(Carbon::parse($doc->expires_at)->startOfDay(), false);
                    $isExpired = $daysLeft <= 0;

                    $priority = ($isExpired || $daysLeft < 7) ? 'critical' : 'high';
                    $title    = $isExpired
                        ? "[{$doc->doc_type}] 合規文件已過期 — {$doc->supplier->name}"
                        : "[{$doc->doc_type}] 合規文件即將到期 — {$doc->supplier->name}";

                    $expiresLabel = $isExpired
                        ? "已於 {$doc->expires_at->toDateString()} 過期"
                        : "將於 {$doc->expires_at->toDateString()} 到期";

                    $finding = "合規文件《{$doc->file_name}》（{$doc->doc_type}）{$expiresLabel}，請更新並上傳最新版本。";

                    $this->log[] = ($this->dryRun ? "DRY   " : "CREATE") . " [{$doc->supplier->name}] {$doc->doc_type} expires={$doc->expires_at->toDateString()} priority={$priority}";

                    if (!$this->dryRun) {
                        $cap = CAP::create([
                            'supplier_id' => $doc->supplier_id,
                            'source_type' => 'compliance_doc',
                            'source_id'   => $doc->id,
                            'title'       => $title,
                            'status'      => 'open',
                            'priority'    => $priority,
                            'due_date'    => $isExpired
                                ? Carbon::now()->addDays(14)->toDateString()
                                : $doc->expires_at->toDateString(),
                        ]);

                        CAPFinding::create([
                            'cap_id'     => $cap->id,
                            'category'   => 'G',
                            'finding'    => $finding,
                            'status'     => 'open',
                        ]);

                        $created++;
                    } else {
                        $created++;
                    }
                }
            });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'log'     => $this->log,
        ];
    }
}
