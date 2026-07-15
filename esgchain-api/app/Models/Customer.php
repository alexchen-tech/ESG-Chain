<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'country_code',
        'eori_number',
        'vat_number',
        'customer_type',
        'address',
        'website',
        'notes',
        'status',
        'created_by',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function tradeGoods(): HasMany
    {
        return $this->hasMany(TradeGood::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
