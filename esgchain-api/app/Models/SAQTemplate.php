<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SAQTemplate extends Model
{
    use HasUuids;

    protected $table = 'saq_templates';

    protected $fillable = ['name', 'version', 'description', 'is_active', 'sasb_industry_id', 'created_by_id', 'archived_at', 'status', 'draft_of', 'scoring_framework'];

    protected $casts = [
        'is_active'   => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(SAQQuestion::class, 'template_id')->orderBy('order');
    }

    public function sasbIndustry(): BelongsTo
    {
        return $this->belongsTo(SasbIndustry::class, 'sasb_industry_id');
    }

    public function industries(): BelongsToMany
    {
        return $this->belongsToMany(SasbIndustry::class, 'saq_template_industries', 'template_id', 'industry_id');
    }

    // 此範本的草稿版（若存在）
    public function draft(): HasOne
    {
        return $this->hasOne(SAQTemplate::class, 'draft_of');
    }

    // 此 draft 所基於的 published 版
    public function publishedOrigin(): BelongsTo
    {
        return $this->belongsTo(SAQTemplate::class, 'draft_of');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }
}
