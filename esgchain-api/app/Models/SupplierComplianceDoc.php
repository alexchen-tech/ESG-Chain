<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierComplianceDoc extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'trade_good_id',
        'doc_type',
        'file_path',
        'file_name',
        'issued_at',
        'expires_at',
        'verified_at',
        'verified_by',
        'notes',
    ];

    protected $casts = [
        'issued_at'   => 'date',
        'expires_at'  => 'date',
        'verified_at' => 'datetime',
    ];

    protected $appends = ['status'];

    public function getStatusAttribute(): string
    {
        if ($this->expires_at === null) {
            return 'pending';
        }

        $now   = Carbon::now()->startOfDay();
        $expiry = $this->expires_at->startOfDay();

        if ($expiry->lte($now)) {
            return 'expired';
        }

        if ($expiry->lte($now->copy()->addDays(30))) {
            return 'expiring_soon';
        }

        return 'valid';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function tradeGood(): BelongsTo
    {
        return $this->belongsTo(TradeGood::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
