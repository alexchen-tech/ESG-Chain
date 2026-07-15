<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialItemEmission extends Model
{
    use HasUuids;

    protected $fillable = [
        'material_item_id',
        'supplier_id',
        'emissions_value',
        'source',
        'calculation_method',
        'reported_period',
        'is_estimated',
        'is_flagged',
        'flag_reason',
        'reported_at',
    ];

    protected $casts = [
        'emissions_value' => 'float',
        'is_estimated'    => 'boolean',
        'is_flagged'      => 'boolean',
        'reported_at'     => 'datetime',
    ];

    public function materialItem(): BelongsTo
    {
        return $this->belongsTo(MaterialItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
