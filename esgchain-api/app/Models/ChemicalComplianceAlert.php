<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChemicalComplianceAlert extends Model
{
    use HasUuids;

    protected $fillable = [
        'sales_product_id', 'material_item_id', 'material_item_chemical_id',
        'chemical_id', 'regulated_list', 'alert_level', 'status',
        'notes', 'acknowledged_at', 'acknowledged_by',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];

    public function materialItem(): BelongsTo
    {
        return $this->belongsTo(MaterialItem::class);
    }

    public function salesProduct(): BelongsTo
    {
        return $this->belongsTo(SalesProduct::class);
    }

    public function chemical(): BelongsTo
    {
        return $this->belongsTo(Chemical::class);
    }

    public function materialItemChemical(): BelongsTo
    {
        return $this->belongsTo(MaterialItemChemical::class);
    }
}
