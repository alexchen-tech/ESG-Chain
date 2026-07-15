<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SasbRequiredTopic extends Model
{
    use HasUuids;

    protected $fillable = [
        'sasb_industry_code',
        'tag_slug',
        'rationale',
    ];
}
