<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeoEventSupplierReview extends Model
{
    use HasUuids;

    protected $fillable = [
        'geo_event_id', 'supplier_id', 'status',
        'pre_e4_score', 'post_e4_score', 'risk_assessment_id',
        'recalculation_started_at', 'error_message',
    ];

    protected $casts = [
        'pre_e4_score'             => 'float',
        'post_e4_score'            => 'float',
        'recalculation_started_at' => 'datetime',
    ];

    public function geoEvent(): BelongsTo
    {
        return $this->belongsTo(GeoEvent::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
