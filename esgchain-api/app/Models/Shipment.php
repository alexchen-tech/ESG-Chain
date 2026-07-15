<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'shipment_no',
        'customer_id',
        'customer_po_no',
        'target_market',
        'export_date',
        'eudr_dds_status',
        'eudr_dds_ref',
        'eudr_submitted_at',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'export_date'       => 'date',
        'eudr_submitted_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ShipmentLine::class);
    }
}
