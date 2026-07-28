<?php

namespace App\Models;

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
        'group_id', 'name', 'code', 'vat_number', 'erp_vendor_codes',
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
