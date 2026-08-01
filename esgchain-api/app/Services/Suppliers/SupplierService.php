<?php

namespace App\Services\Suppliers;

use App\Models\RiskAssessment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class SupplierService
{
    public function list(array $filters = [], ?User $user = null): LengthAwarePaginator
    {
        $withRelations = ['group', 'organizationUnit', 'contacts' => fn($q) => $q->where('is_primary', true)];
        if (!empty($filters['include_risk'])) {
            $withRelations[] = 'latestRiskAssessment';
        }

        $query = Supplier::with($withRelations)
            ->when($user, fn($q) => $q->visibleTo($user))
            ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
            ->when($filters['onboarding_stage'] ?? null, fn($q, $v) => $q->where('onboarding_stage', $v))
            ->when($filters['tier'] ?? null, fn($q, $v) => $q->where('tier', $v))
            ->when($filters['supplier_group_id'] ?? null, fn($q, $v) => $q->where('group_id', $v))
            ->when($filters['country_code'] ?? null, fn($q, $v) => $q->where('country_code', $v))
            ->when($filters['sasb_industry_id'] ?? null, fn($q, $v) => $q->where('sasb_industry_id', $v))
            ->when($filters['organization_unit_id'] ?? null, fn($q, $v) => $q->where('organization_unit_id', $v))
            ->when($filters['q'] ?? null, fn($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('name', 'like', "%$v%")->orWhere('code', 'like', "%$v%");
            }))
            ->when(
                !empty($filters['risk_dim']) && !empty($filters['risk_probability']) && !empty($filters['risk_impact']),
                function ($q) use ($filters) {
                    $dim  = strtolower($filters['risk_dim']);
                    $prob = (int) $filters['risk_probability'];
                    $imp  = (int) $filters['risk_impact'];

                    // 每個供應商只取最新一筆 RiskAssessment
                    $latestSub = RiskAssessment::selectRaw('supplier_id, MAX(assessed_at) as max_at')
                        ->groupBy('supplier_id');

                    $matchIds = RiskAssessment::joinSub($latestSub, 'latest_ra', function ($join) {
                        $join->on('risk_assessments.supplier_id', '=', 'latest_ra.supplier_id')
                             ->on('risk_assessments.assessed_at', '=', 'latest_ra.max_at');
                    })
                        ->where("risk_assessments.{$dim}_probability", $prob)
                        ->where("risk_assessments.{$dim}_impact", $imp)
                        ->pluck('risk_assessments.supplier_id');

                    $q->whereIn('id', $matchIds);
                }
            )
            ->orderBy('created_at', 'desc');

        return $query->paginate($filters['per_page'] ?? 20, ['*'], 'page', $filters['page'] ?? 1);
    }

    public function create(array $data, string $userId): Supplier
    {
        $supplier = Supplier::create($data);

        $supplier->statusHistories()->create([
            'type' => 'erp_status',
            'from_status' => null,
            'to_status' => $supplier->status,
            'reason' => '供應商建立（資料匯入時預設值）',
            'changed_by' => $userId,
        ]);

        if (!empty($data['contacts'])) {
            foreach ($data['contacts'] as $contact) {
                $supplier->contacts()->create($contact);
            }
        }

        return $supplier->load('contacts');
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);
        return $supplier->fresh(['group', 'contacts']);
    }

    public function transitionStatus(Supplier $supplier, string $newStatus, ?string $reason, string $userId): Supplier
    {
        $supplier->transitionStatus($newStatus, $reason, $userId);
        return $supplier->fresh();
    }

    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
    }

    /**
     * 指派/變更/清空供應商的組織單位歸屬，每次變更寫入一筆稽核紀錄（比照 SupplierStatusHistory 模式）。
     */
    public function assignOrganizationUnit(Supplier $supplier, ?string $organizationUnitId, string $changedBy): Supplier
    {
        $fromId = $supplier->organization_unit_id;

        $supplier->update(['organization_unit_id' => $organizationUnitId]);

        $supplier->organizationUnitHistories()->create([
            'from_organization_unit_id' => $fromId,
            'to_organization_unit_id'   => $organizationUnitId,
            'changed_by'                => $changedBy,
        ]);

        return $supplier->fresh(['group', 'organizationUnit', 'organizationUnitHistories.fromOrganizationUnit', 'organizationUnitHistories.toOrganizationUnit']);
    }
}
