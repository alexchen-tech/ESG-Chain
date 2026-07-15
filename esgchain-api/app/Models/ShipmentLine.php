<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'shipment_id',
        'sales_product_id',
        'pcf_snapshot_id',
        'total_quantity',
        'unit',
        'hs_code_override',
        'weighted_pcf',
    ];

    protected $casts = [
        'total_quantity' => 'decimal:4',
        'weighted_pcf'   => 'decimal:4',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function salesProduct(): BelongsTo
    {
        return $this->belongsTo(SalesProduct::class, 'sales_product_id');
    }

    public function lineBatches(): HasMany
    {
        return $this->hasMany(ShipmentLineBatch::class);
    }
}
