<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBatterySpec extends Model
{
    use HasUuids;

    protected $fillable = [
        'sales_product_id',
        'battery_category',
        'chemistry',
        'rated_capacity_ah',
        'rated_voltage_v',
        'weight_kg',
        'lithium_recycled_content_ratio',
        'cobalt_recycled_content_ratio',
        'nickel_recycled_content_ratio',
        'lead_recycled_content_ratio',
        'cycle_life',
        'expected_lifetime_years',
        'discharge_efficiency_ratio',
        'initial_capacity_soh_note',
        'operating_temp_range',
    ];

    protected $casts = [
        'rated_capacity_ah'              => 'float',
        'rated_voltage_v'                => 'float',
        'weight_kg'                      => 'float',
        'lithium_recycled_content_ratio' => 'float',
        'cobalt_recycled_content_ratio'  => 'float',
        'nickel_recycled_content_ratio'  => 'float',
        'lead_recycled_content_ratio'    => 'float',
        'cycle_life'                     => 'integer',
        'expected_lifetime_years'        => 'integer',
        'discharge_efficiency_ratio'     => 'float',
    ];

    public function salesProduct(): BelongsTo
    {
        return $this->belongsTo(SalesProduct::class);
    }
}
