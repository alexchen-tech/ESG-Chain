<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketComplianceRule extends Model
{
    use HasUuids;

    protected $fillable = [
        'market',
        'doc_type',
        'scope',
        'is_mandatory',
        'effective_from',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_mandatory'   => 'boolean',
        'is_active'      => 'boolean',
        'effective_from' => 'date',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
                     ->where('effective_from', '<=', now()->toDateString());
    }

    public function scopeForMarket(Builder $query, string $market): Builder
    {
        return $query->where('market', $market);
    }
}
