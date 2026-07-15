<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeGoodPathRisk extends Model
{
    use HasUuids;

    protected $table = 'trade_good_path_risks';

    protected $fillable = [
        'trade_good_id', 'market',
        'path_risk_score', 'risk_level', 'amplifier', 'chain_risk',
        'has_data_gap', 'contributors', 'calculated_at', 'expires_at',
    ];

    protected $casts = [
        'path_risk_score' => 'float',
        'amplifier'       => 'float',
        'chain_risk'      => 'float',
        'has_data_gap'    => 'boolean',
        'contributors'    => 'array',
        'calculated_at'   => 'datetime',
        'expires_at'      => 'datetime',
    ];

    public function tradeGood(): BelongsTo
    {
        return $this->belongsTo(TradeGood::class, 'trade_good_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
