<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuyerProductTradeGood extends Model
{
    use HasUuids;

    protected $fillable = [
        'buyer_product_id',
        'trade_good_id',
        'relation_type',
        'bom_line_id',
        'note',
        'erp_product_code',
    ];

    public function buyerProduct(): BelongsTo
    {
        return $this->belongsTo(BuyerProduct::class);
    }

    public function tradeGood(): BelongsTo
    {
        return $this->belongsTo(TradeGood::class);
    }

    public function bomLine(): BelongsTo
    {
        return $this->belongsTo(ProductBomLine::class, 'bom_line_id');
    }

    public function productionBatches(): HasMany
    {
        return $this->hasMany(ProductionBatch::class, 'buyer_product_trade_good_id');
    }
}
