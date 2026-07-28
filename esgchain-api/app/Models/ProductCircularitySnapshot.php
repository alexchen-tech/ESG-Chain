<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCircularitySnapshot extends Model
{
    use HasUuids;

    protected $fillable = [
        'sales_product_id',
        'total_weight_kg',
        'recycled_content_ratio',
        'pcr_ratio',
        'pir_ratio',
        'bio_based_ratio',
        'composition_breakdown',
        'recyclability_summary',
        'incomplete_lines_count',
        'data_ready',
        'calculated_at',
    ];

    protected $casts = [
        'total_weight_kg'         => 'float',
        'recycled_content_ratio'  => 'float',
        'pcr_ratio'               => 'float',
        'pir_ratio'               => 'float',
        'bio_based_ratio'         => 'float',
        'composition_breakdown'   => 'array',
        'recyclability_summary'   => 'array',
        'incomplete_lines_count'  => 'integer',
        'data_ready'              => 'boolean',
        'calculated_at'           => 'datetime',
    ];

    public function salesProduct(): BelongsTo
    {
        return $this->belongsTo(SalesProduct::class, 'sales_product_id');
    }
}
