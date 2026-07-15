<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionTag extends Model
{
    use HasUuids;

    protected $table = 'question_tags';

    protected $fillable = [
        'l1_domain', 'l2_pillar', 'l3_topic',
        'slug', 'label_zh', 'label_en',
        'scoring_engine_key', 'default_weight', 'deprecated_at', 'sort_order',
    ];

    protected $casts = [
        'deprecated_at'  => 'datetime',
        'sort_order'     => 'integer',
        'default_weight' => 'float',
    ];

    /** L2 節點：l3_topic='General' AND l2_pillar!='General' */
    public function scopeL2($query)
    {
        return $query->where('l3_topic', 'General')->where('l2_pillar', '!=', 'General');
    }

    /** L3 節點：l3_topic!='General' */
    public function scopeL3($query)
    {
        return $query->where('l3_topic', '!=', 'General');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deprecated_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(QuestionTagAssignment::class, 'tag_id');
    }
}
