<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Chemical extends Model
{
    use HasUuids;

    protected $fillable = [
        'cas_no', 'substance_name', 'iupac_name',
        'regulated_lists', 'restriction_notes', 'svhc_date', 'synced_at',
    ];

    protected $casts = [
        'regulated_lists' => 'array',
        'svhc_date'       => 'date',
        'synced_at'       => 'datetime',
    ];
}
