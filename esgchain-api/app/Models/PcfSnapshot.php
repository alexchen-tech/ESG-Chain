<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PcfSnapshot extends Model
{
    use HasUuids;

    protected $fillable = [
        'sales_product_id',
        'total_pcf',
        'functional_unit',
        'iso14067_ready',
        'snapshot_at',
        'lines',
        'pcr_ratio',
        'pcr_incomplete_lines',
    ];

    protected $casts = [
        'total_pcf'            => 'float',
        'iso14067_ready'       => 'boolean',
        'snapshot_at'          => 'datetime',
        'lines'                => 'array',
        'pcr_ratio'            => 'float',
        'pcr_incomplete_lines' => 'integer',
    ];

    public function salesProduct(): BelongsTo
    {
        return $this->belongsTo(SalesProduct::class, 'sales_product_id');
    }
}
