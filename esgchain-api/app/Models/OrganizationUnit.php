<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationUnit extends Model
{
    use HasUuids;

    protected $table = 'organization_units';

    protected $fillable = [
        'name', 'code', 'type', 'parent_id', 'country_code',
        'depth', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'depth'     => 'integer',
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrganizationUnit::class, 'parent_id')->orderBy('sort_order');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'organization_unit_id');
    }
}
