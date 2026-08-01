<?php

namespace App\Models;

use App\Services\OrganizationUnit\OrganizationUnitScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    // status = active | inactive（ERP 擁有，依匯入資料決定，不可手動修改；active 為匯入時預設值）
    const STATUSES = ['active', 'inactive'];
    // ESG-Chain 擁有：供應商合作狀態（與 ERP status 語意一致）
    const ONBOARDING_STAGES = ['active', 'suspended', 'terminated'];

    private const ONBOARDING_TRANSITIONS = [
        'active'     => ['suspended'],
        'suspended'  => ['active', 'terminated'],
        'terminated' => [],
    ];

    protected $fillable = [
        'group_id', 'organization_unit_id', 'name', 'code', 'vat_number', 'erp_vendor_codes',
        'country_code', 'industry', 'industry_group', 'sasb_industry_id', 'tier',
        'status', 'onboarding_stage', 'risk_score', 'impact_score', 'spend_amount',
        'tags', 'profile_completed', 'address', 'website',
    ];

    protected $casts = [
        'tier'              => 'integer',
        'risk_score'        => 'float',
        'impact_score'      => 'integer',
        'spend_amount'      => 'float',
        'erp_vendor_codes'  => 'array',
        'tags'              => 'array',
        'profile_completed' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SupplierGroup::class, 'group_id');
    }

    public function sasbIndustry(): BelongsTo
    {
        return $this->belongsTo(SasbIndustry::class, 'sasb_industry_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(SupplierStatusHistory::class);
    }

    // organization_unit_id 為 ESG-Chain 自有營運欄位，永不隨 ERP 同步覆蓋（見 ErpSyncService::ERP_OWNED_SUPPLIER_FIELDS）
    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'organization_unit_id');
    }

    public function organizationUnitHistories(): HasMany
    {
        return $this->hasMany(SupplierOrganizationUnitHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * 依使用者所屬組織單位的可視子樹過濾供應商清單。刻意設計為 local scope 而非 global scope，
     * 避免 ERP 同步內部查詢、admin 稽核匯出等場景被隱性套用範圍限制（見 design.md Decision 2）。
     *
     * 過濾規則：
     * - $user->organization_unit_id 為 null（如 admin）→ 視為全域可見，不套用過濾
     * - 否則 → organization_unit_id 為 null（未指派單位）OR 屬於使用者可視子樹，兩者皆可見
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->organization_unit_id === null) {
            return $query;
        }

        $visibleUnitIds = app(OrganizationUnitScopeService::class)->visibleUnitIds($user);

        return $query->where(function (Builder $q) use ($visibleUnitIds) {
            $q->whereNull('organization_unit_id')
                ->orWhereIn('organization_unit_id', $visibleUnitIds);
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function complianceDocs(): HasMany
    {
        return $this->hasMany(SupplierComplianceDoc::class);
    }

    public function tradeGoods(): HasMany
    {
        return $this->hasMany(TradeGood::class);
    }

    public function bomLineSuppliers(): HasMany
    {
        return $this->hasMany(BomLineSupplier::class);
    }

    public function materialItemSuppliers(): HasMany
    {
        return $this->hasMany(MaterialItemSupplier::class);
    }

    public function productionBatches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class);
    }

    public function latestRiskAssessment(): HasOne
    {
        return $this->hasOne(RiskAssessment::class)->latestOfMany('assessed_at');
    }

    public function riskAssessments(): HasMany
    {
        return $this->hasMany(RiskAssessment::class);
    }

    public function transitionStatus(string $newStatus, ?string $reason, ?string $changedBy): void
    {
        $oldStatus = $this->status;
        $this->update(['status' => $newStatus]);
        $this->statusHistories()->create([
            'type' => 'erp_status',
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'reason' => $reason,
            'changed_by' => $changedBy,
        ]);
    }

    public function transitionOnboardingStage(string $newStage, ?string $reason, string $changedBy): void
    {
        $allowed = self::ONBOARDING_TRANSITIONS[$this->onboarding_stage] ?? [];
        if (!in_array($newStage, $allowed, true)) {
            abort(422, "不允許從 {$this->onboarding_stage} 轉換至 {$newStage}");
        }
        $oldStage = $this->onboarding_stage;
        $this->update(['onboarding_stage' => $newStage]);
        $this->statusHistories()->create([
            'type'        => 'onboarding',
            'from_status' => $oldStage,
            'to_status'   => $newStage,
            'reason'      => $reason,
            'changed_by'  => $changedBy,
        ]);
    }

    public function allowedOnboardingTransitions(): array
    {
        return self::ONBOARDING_TRANSITIONS[$this->onboarding_stage] ?? [];
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(SupplierFacility::class);
    }

    /**
     * 供應商僅有單一廠區時，該廠區即視為預設廠區（不需使用者手動指定）；
     * 有多個廠區時無法判斷該用哪一個，回傳 null 交由使用者自行選擇。
     */
    public function defaultFacility(): ?SupplierFacility
    {
        $facilities = $this->relationLoaded('facilities') ? $this->facilities : $this->facilities()->get();

        return $facilities->count() === 1 ? $facilities->first() : null;
    }
}
