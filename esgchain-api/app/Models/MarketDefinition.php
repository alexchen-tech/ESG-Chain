<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MarketDefinition extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'label',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];
}
