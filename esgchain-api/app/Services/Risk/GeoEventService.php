<?php

namespace App\Services\Risk;

use App\Models\GeoEvent;
use App\Models\GeoEventSupplierReview;
use App\Models\RiskAssessment;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoEventService
{
    public function create(array $data, ?string $userId): GeoEvent
    {
        $event = GeoEvent::create([
            'name'           => $data['name'],
            'event_type'     => $data['event_type'],
            'affected_scope' => $data['affected_scope'] ?? [],
            'severity'       => $data['severity'],
            'occurred_at'    => $data['occurred_at'],
            'created_by_id'  => $userId,
        ]);

        $this->createSupplierReviews($event);

        return $event;
    }

    private function createSupplierReviews(GeoEvent $event): void
    {
        $scope = $event->affected_scope ?? [];
        $countryCodes = $scope['country_codes'] ?? [];

        if (empty($countryCodes)) {
            return;
        }

        // HQ 在受影響國家的供應商
        $hqSupplierIds = Supplier::whereIn('country_code', $countryCodes)
            ->pluck('id')->all();

        // 有 active 廠址在受影響國家的供應商
        $facilitySupplierIds = DB::table('supplier_facilities')
            ->whereIn('country', $countryCodes)
            ->where('is_active', true)
            ->pluck('supplier_id')->all();

        $supplierIds = array_unique(array_merge($hqSupplierIds, $facilitySupplierIds));

        if (empty($supplierIds)) {
            return;
        }

        // 批次取最新 RA 的 dim_e4
        $latestRas = RiskAssessment::select('supplier_id', 'dim_e4')
            ->whereIn('supplier_id', $supplierIds)
            ->orderBy('assessed_at', 'desc')
            ->get()
            ->keyBy('supplier_id');

        $rows = [];
        $now = now();
        foreach ($supplierIds as $supplierId) {
            $rows[] = [
                'id'           => \Str::uuid()->toString(),
                'geo_event_id' => $event->id,
                'supplier_id'  => $supplierId,
                'status'       => 'pending',
                'pre_e4_score' => $latestRas->get($supplierId)?->dim_e4,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        GeoEventSupplierReview::insert($rows);
    }

    public function dispatchRecalculation(GeoEvent $event): void
    {
        $pendingReviews = GeoEventSupplierReview::where('geo_event_id', $event->id)
            ->where('status', 'pending')
            ->get();

        if ($pendingReviews->isEmpty()) {
            return;
        }

        $now = Carbon::now();
        GeoEventSupplierReview::where('geo_event_id', $event->id)
            ->where('status', 'pending')
            ->update(['status' => 'recalculating', 'recalculation_started_at' => $now]);

        $supplierIds = $pendingReviews->pluck('supplier_id')->all();

        $aiUrl = rtrim(config('services.ai.url', config('services.esgchain_ai.url', 'http://esgchain-ai:8000')), '/');

        try {
            Http::withHeaders(['X-Internal-Token' => config('services.ai.internal_token')])
                ->timeout(10)->post("{$aiUrl}/ai/v1/geo-event/recalculate-e4", [
                'geo_event_id' => $event->id,
                'supplier_ids' => $supplierIds,
                'callback_url' => url("/api/v1/risk/geo-events/{$event->id}/review-callback"),
            ]);
        } catch (\Throwable $e) {
            Log::error('GeoEventService: dispatch recalculation failed', [
                'geo_event_id' => $event->id,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    public function handleReviewCallback(GeoEvent $event, array $results): void
    {
        // results: [{ supplier_id, dim_e4, dim_e1, ..., dim_e6 }, ...]
        foreach ($results as $result) {
            $supplierId = $result['supplier_id'] ?? null;
            if (!$supplierId) continue;

            $review = GeoEventSupplierReview::where('geo_event_id', $event->id)
                ->where('supplier_id', $supplierId)
                ->where('status', 'recalculating')
                ->first();

            if (!$review) continue;

            $ra = RiskAssessment::create([
                'supplier_id'        => $supplierId,
                'assessed_at'        => Carbon::now(),
                'source_type'        => 'geo_event',
                'source_id'          => $event->id,
                'dim_e1'             => $result['dim_e1'] ?? null,
                'dim_e2'             => $result['dim_e2'] ?? null,
                'dim_e3'             => $result['dim_e3'] ?? null,
                'dim_e4'             => $result['dim_e4'] ?? null,
                'dim_e5'             => $result['dim_e5'] ?? null,
                'dim_e6'             => $result['dim_e6'] ?? null,
                'assessment_version' => 'v3',
                'notes'              => "地緣事件重算：{$event->name}",
            ]);

            $review->update([
                'status'            => 'done',
                'post_e4_score'     => $result['dim_e4'] ?? null,
                'risk_assessment_id' => $ra->id,
            ]);
        }
    }
}
