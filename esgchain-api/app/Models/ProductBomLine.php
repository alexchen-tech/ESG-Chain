<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductBomLine extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $fillable = [
        'sales_product_id',
        'child_sales_product_id',
        'material_item_id',
        'erp_line_id',
        'material_name',
        'hs_code',
        'material_group_id',
        'material_group_source',
        'linkage_status',
        'bom_line_type',
        'quantity',
        'unit',
        'unit_price',
        'currency',
        'notes',
    ];

    protected $casts = [
        'quantity'   => 'float',
        'unit_price' => 'float',
    ];

    public function salesProduct(): BelongsTo
    {
        return $this->belongsTo(SalesProduct::class, 'sales_product_id');
    }

    public function childSalesProduct(): BelongsTo
    {
        return $this->belongsTo(SalesProduct::class, 'child_sales_product_id');
    }

    public function materialGroup(): BelongsTo
    {
        return $this->belongsTo(MaterialGroup::class);
    }

    public function materialItem(): BelongsTo
    {
        return $this->belongsTo(MaterialItem::class);
    }

    public function bomLineSuppliers(): HasMany
    {
        return $this->hasMany(BomLineSupplier::class, 'bom_line_id')->orderBy('sort_order');
    }

    public function primarySupplier(): HasMany
    {
        return $this->hasMany(BomLineSupplier::class, 'bom_line_id')->where('role', 'primary');
    }
}
