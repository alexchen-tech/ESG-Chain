<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionBatch extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'erp_batch_no',
        'erp_order_no',
        'buyer_product_trade_good_id',
        'supplier_id',
        'sales_product_id',
        'production_date',
        'quantity',
        'unit',
        'lot_pcf',
        'lot_pcf_source',
        'source',
        'erp_synced_at',
    ];

    protected $casts = [
        'production_date' => 'date',
        'quantity'        => 'decimal:4',
        'lot_pcf'         => 'decimal:4',
        'erp_synced_at'   => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function salesProduct(): BelongsTo
    {
        return $this->belongsTo(SalesProduct::class);
    }

    public function exportLink(): BelongsTo
    {
        return $this->belongsTo(BuyerProductTradeGood::class, 'buyer_product_trade_good_id');
    }

    public function rawMaterialOrigins(): HasMany
    {
        return $this->hasMany(RawMaterialOrigin::class);
    }

    public function shipmentLineBatches(): HasMany
    {
        return $this->hasMany(ShipmentLineBatch::class);
    }
}
