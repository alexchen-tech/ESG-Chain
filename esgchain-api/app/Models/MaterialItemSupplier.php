<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialItemSupplier extends Model
{
    use HasUuids;

    protected $fillable = [
        'material_item_id',
        'supplier_id',
        'supplier_facility_id',
        'role',
        'source',
        'sort_order',
    ];

    public function materialItem(): BelongsTo
    {
        return $this->belongsTo(MaterialItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierFacility(): BelongsTo
    {
        return $this->belongsTo(SupplierFacility::class);
    }
}
