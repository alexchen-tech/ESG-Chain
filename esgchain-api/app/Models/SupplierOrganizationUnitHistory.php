<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOrganizationUnitHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['supplier_id', 'from_organization_unit_id', 'to_organization_unit_id', 'changed_by'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $history) {
            $history->created_at ??= now();
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function fromOrganizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'from_organization_unit_id');
    }

    public function toOrganizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'to_organization_unit_id');
    }
}
