<?php

namespace App\Services\Supplier;

use App\Models\SupplierFacility;

class SupplierFacilityService
{
    public function list(string $supplierId): array
    {
        $facilities = SupplierFacility::where('supplier_id', $supplierId)
            ->with(['activityReports' => fn($q) => $q->latest()->limit(1)])
            ->get();

        return $facilities->map(function ($facility) {
            $latest = $facility->activityReports->first();
            return array_merge($facility->toArray(), [
                'latest_report_status' => $latest?->status,
                'latest_report_period' => $latest?->report_period,
            ]);
        })->values()->toArray();
    }

    public function create(string $supplierId, array $data): SupplierFacility
    {
        return SupplierFacility::create(array_merge($data, ['supplier_id' => $supplierId]));
    }

    public function update(SupplierFacility $facility, array $data): SupplierFacility
    {
        $facility->update($data);
        return $facility->fresh();
    }
}
