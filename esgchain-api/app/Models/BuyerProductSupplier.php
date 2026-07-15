<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyerProductSupplier extends Model
{
    use HasUuids;

    protected $fillable = [
        'buyer_product_id',
        'supplier_id',
    ];

    public function buyerProduct(): BelongsTo
    {
        return $this->belongsTo(BuyerProduct::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
