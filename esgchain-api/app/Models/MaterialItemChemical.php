<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialItemChemical extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'material_item_id', 'cas_no', 'weight_percentage',
        'reporting_threshold', 'source',
    ];

    protected $casts = [
        'weight_percentage'   => 'float',
        'reporting_threshold' => 'float',
    ];

    public function materialItem(): BelongsTo
    {
        return $this->belongsTo(MaterialItem::class);
    }

    public function chemical(): BelongsTo
    {
        return $this->belongsTo(Chemical::class, 'cas_no', 'cas_no');
    }
}
