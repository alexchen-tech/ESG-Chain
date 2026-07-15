<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeGoodSupplier extends Model
{
    use HasUuids;

    protected $fillable = [
        'trade_good_id', 'supplier_id', 'material_group_id', 'notes',
    ];

    public function tradeGood(): BelongsTo
    {
        return $this->belongsTo(TradeGood::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function materialGroup(): BelongsTo
    {
        return $this->belongsTo(MaterialGroup::class);
    }
}
