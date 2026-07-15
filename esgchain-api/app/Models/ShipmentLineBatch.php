<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentLineBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'shipment_line_id',
        'production_batch_id',
        'allocated_quantity',
    ];

    protected $casts = [
        'allocated_quantity' => 'decimal:4',
    ];

    public function shipmentLine(): BelongsTo
    {
        return $this->belongsTo(ShipmentLine::class);
    }

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class);
    }
}
