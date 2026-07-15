<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeGoodSupplierEmission extends Model
{
    use HasUuids;

    protected $fillable = [
        'trade_good_id', 'supplier_id', 'emissions_value', 'calculation_note',
        'reported_at', 'confirmed_at',
    ];

    protected $casts = [
        'emissions_value' => 'float',
        'reported_at'     => 'datetime',
        'confirmed_at'    => 'datetime',
    ];

    public function tradeGood(): BelongsTo
    {
        return $this->belongsTo(TradeGood::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
