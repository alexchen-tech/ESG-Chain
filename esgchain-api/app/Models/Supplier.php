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

    // spec v2.1.0: status = active | inactive | suspended（ERP 擁有，不可手動修改）
    const STATUSES = ['active', 'inactive', 'suspended'];
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

    public function productionBatches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class);
    }

    public function latestRiskAssessment(): HasOne
    {
        return $this->hasOne(RiskAssessment::class)->latestOfMany('assessed_at');
    }

    public function transitionStatus(string $newStatus, ?string $reason, string $changedBy): void
    {
        $oldStatus = $this->status;
        $this->update(['status' => $newStatus]);
        $this->statusHistories()->create([
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
}
