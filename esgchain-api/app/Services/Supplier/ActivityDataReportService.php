<?php

namespace App\Services\Supplier;

use App\Jobs\Scope3PushJob;
use App\Models\ActivityDataReport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ActivityDataReportService
{
    /**
     * 中心廠端跨供應商彙總列表（分頁）。
     */
    public function paginateAll(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $query = ActivityDataReport::query()
            ->with(['facility:id,name,supplier_id', 'facility.supplier:id,name']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['report_period'])) {
            $query->where('report_period', $filters['report_period']);
        }

        if (!empty($filters['supplier_id'])) {
            $query->whereHas('facility', fn ($q) => $q->where('supplier_id', $filters['supplier_id']));
        }

        if (!empty($filters['push_status'])) {
            match ($filters['push_status']) {
                'not_pushed' => $query->whereNull('push_log'),
                'success'    => $query->whereNotNull('push_log')->where('push_log->status', 'success'),
                'failed'     => $query->whereNotNull('push_log')->where('push_log->status', 'failed'),
                default      => null,
            };
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * 中心廠端彙總看板統計：狀態別、推送狀態別筆數。
     */
    public function summary(): array
    {
        $statusCounts = ActivityDataReport::query()
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $total = (int) $statusCounts->sum();

        $notPushed = ActivityDataReport::whereNull('push_log')->count();
        $success   = ActivityDataReport::whereNotNull('push_log')->where('push_log->status', 'success')->count();
        $failed    = ActivityDataReport::whereNotNull('push_log')->where('push_log->status', 'failed')->count();

        return [
            'total' => $total,
            'by_status' => [
                'draft'     => (int) ($statusCounts['draft'] ?? 0),
                'submitted' => (int) ($statusCounts['submitted'] ?? 0),
                'verified'  => (int) ($statusCounts['verified'] ?? 0),
            ],
            'by_push_status' => [
                'not_pushed' => $notPushed,
                'success'    => $success,
                'failed'     => $failed,
            ],
        ];
    }

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
