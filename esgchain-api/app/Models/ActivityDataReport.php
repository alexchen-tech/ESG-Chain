<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityDataReport extends Model
{
    use HasUuids;

    protected $fillable = [
        'supplier_facility_id',
        'report_period',
        'electricity_kwh',
        'natural_gas_gj',
        'fuel_oil_l',
        'heat_gj',
        'water_m3',
        'notes',
        'status',
        'push_log',
        'submitted_at',
        'verified_at',
    ];

    protected $casts = [
        'electricity_kwh' => 'float',
        'natural_gas_gj'  => 'float',
        'fuel_oil_l'      => 'float',
        'heat_gj'         => 'float',
        'water_m3'        => 'float',
        'push_log'        => 'array',
        'submitted_at'    => 'datetime',
        'verified_at'     => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(SupplierFacility::class, 'supplier_facility_id');
    }
}
