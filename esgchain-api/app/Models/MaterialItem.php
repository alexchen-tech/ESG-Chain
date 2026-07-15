<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialItem extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $fillable = [
        'item_code',
        'name',
        'hs_code',
        'unit',
        'material_group_id',
        'description',
        'is_active',
        'net_weight',
        'pcr_percentage',
        'pir_percentage',
        'bio_based_percentage',
        'recyclability_rating',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'net_weight'           => 'float',
        'pcr_percentage'       => 'float',
        'pir_percentage'       => 'float',
        'bio_based_percentage' => 'float',
    ];

    public function materialGroup(): BelongsTo
    {
        return $this->belongsTo(MaterialGroup::class);
    }

    public function bomLines(): HasMany
    {
        return $this->hasMany(ProductBomLine::class);
    }

    public function emissions(): HasMany
    {
        return $this->hasMany(MaterialItemEmission::class);
    }

    public function latestEmissionForSupplier(string $supplierId): ?MaterialItemEmission
    {
        return $this->emissions()
            ->where('supplier_id', $supplierId)
            ->latest('reported_at')
            ->first();
    }

    // net_weight and pcr_percentage are ESG-Chain owned, never overwritten by ERP sync

    public function chemicals(): HasMany
    {
        return $this->hasMany(MaterialItemChemical::class);
    }

    public function complianceAlerts(): HasMany
    {
        return $this->hasMany(ChemicalComplianceAlert::class);
    }
}
