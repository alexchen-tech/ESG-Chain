<?php

namespace App\Services\Supplier;

use App\Jobs\Scope3PushJob;
use App\Models\ActivityDataReport;
use Carbon\Carbon;

class ActivityDataReportService
{
    public function create(string $facilityId, array $data): ActivityDataReport
    {
        return ActivityDataReport::create(array_merge($data, [
            'supplier_facility_id' => $facilityId,
            'status'               => 'draft',
        ]));
    }

    public function submit(ActivityDataReport $report): ActivityDataReport
    {
        $report->update([
            'status'       => 'submitted',
            'submitted_at' => Carbon::now(),
        ]);
        return $report->fresh();
    }

    public function verify(ActivityDataReport $report): ActivityDataReport
    {
        $report->update([
            'status'      => 'verified',
            'verified_at' => Carbon::now(),
        ]);
        Scope3PushJob::dispatch($report->id);
        return $report->fresh();
    }

    public function retryPush(ActivityDataReport $report): void
    {
        Scope3PushJob::dispatch($report->id);
    }
}
