<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawMaterialOrigin extends Model
{
    use HasUuids;

    protected $fillable = [
        'production_batch_id',
        'bom_line_id',
        'supplier_id',
        'material_name',
        'origin_country',
        'facility_name',
        'gps_lat',
        'gps_lng',
        'harvest_year',
        'certification_ref',
        'transport_mode',
        'transport_distance_km',
    ];

    protected $casts = [
        'gps_lat'                => 'decimal:6',
        'gps_lng'                => 'decimal:6',
        'harvest_year'           => 'integer',
        'transport_distance_km'  => 'float',
    ];

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class);
    }

    public function bomLine(): BelongsTo
    {
        return $this->belongsTo(ProductBomLine::class, 'bom_line_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
