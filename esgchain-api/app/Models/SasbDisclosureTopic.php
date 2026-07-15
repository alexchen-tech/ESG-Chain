<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SasbDisclosureTopic extends Model
{
    use HasUuids;

    protected $table = 'sasb_disclosure_topics';

    protected $fillable = ['sasb_industry_id', 'topic_name', 'topic_code', 'esg_category', 'description'];

    public function industry(): BelongsTo
    {
        return $this->belongsTo(SasbIndustry::class, 'sasb_industry_id');
    }
}
