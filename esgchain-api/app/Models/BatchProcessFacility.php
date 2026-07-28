<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchProcessFacility extends Model
{
    use HasUuids;

    protected $fillable = [
        'production_batch_id',
        'process_type',
        'supplier_id',
        'supplier_facility_id',
    ];

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class);
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
