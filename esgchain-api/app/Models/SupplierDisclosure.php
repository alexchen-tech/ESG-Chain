<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierDisclosure extends Model
{
    use HasUuids;

    protected $fillable = [
        'supplier_id', 'field_slug', 'period_year',
        'numeric_value', 'boolean_value', 'text_value',
        'evidence_url', 'source', 'source_saq_id', 'verified_at',
    ];

    protected $casts = [
        'period_year'   => 'integer',
        'numeric_value' => 'float',
        'boolean_value' => 'boolean',
        'verified_at'   => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(SupplierDisclosureField::class, 'field_slug', 'slug');
    }

    public function sourceSaq(): BelongsTo
    {
        return $this->belongsTo(SAQ::class, 'source_saq_id');
    }
}
