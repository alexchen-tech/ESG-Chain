<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierFacility extends Model
{
    use HasUuids;

    protected $fillable = [
        'supplier_id',
        'name',
        'country',
        'address',
        'facility_type',
        'energy_types',
        'main_products',
        'is_active',
    ];

    protected $casts = [
        'energy_types' => 'array',
        'is_active'    => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function activityReports(): HasMany
    {
        return $this->hasMany(ActivityDataReport::class);
    }

    public function latestReport(): HasMany
    {
        return $this->hasMany(ActivityDataReport::class)->latest()->limit(1);
    }
}
