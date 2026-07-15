<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CountryRiskRating extends Model
{
    use HasUuids;

    protected $fillable = [
        'country_code',
        'country_name',
        'labor_risk',
        'env_risk',
        'geo_risk',
        'sub_scores',
        'source',
    ];

    protected $casts = [
        'labor_risk' => 'integer',
        'env_risk'   => 'integer',
        'geo_risk'   => 'integer',
        'sub_scores' => 'array',
    ];
}
