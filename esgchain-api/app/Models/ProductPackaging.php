<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPackaging extends Model
{
    use HasUuids;

    protected $fillable = [
        'sales_product_id',
        'recycled_content_ratio',
        'recyclable',
        'reusable',
        'material_description',
        'notes',
    ];

    protected $casts = [
        'recycled_content_ratio' => 'float',
        'recyclable'             => 'boolean',
        'reusable'               => 'boolean',
    ];

    public function salesProduct(): BelongsTo
    {
        return $this->belongsTo(SalesProduct::class);
    }
}
