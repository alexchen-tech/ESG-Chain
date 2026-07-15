<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeoEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'event_type', 'affected_scope', 'severity', 'status', 'occurred_at', 'created_by_id',
    ];

    protected $casts = [
        'affected_scope' => 'array',
        'occurred_at'    => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function supplierReviews(): HasMany
    {
        return $this->hasMany(GeoEventSupplierReview::class);
    }
}
