<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentSeries extends Model
{
    use HasUuids;

    protected $table = 'assessment_series';

    protected $fillable = ['name', 'description', 'domain', 'status', 'created_by_id', 'template_id', 'template_version_at_creation', 'pillar_weights', 'grade_thresholds', 'dim_weights', 'dim_weights_source', 'e4_objective_ratio'];

    protected $casts = [
        'pillar_weights'     => 'array',
        'grade_thresholds'   => 'array',
        'dim_weights'        => 'array',
        'e4_objective_ratio' => 'float',
    ];

    protected $attributes = ['status' => 'active'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SAQTemplate::class, 'template_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(SaqProject::class, 'series_id');
    }

    public function weights(): HasMany
    {
        return $this->hasMany(AssessmentSeriesWeight::class, 'series_id');
    }
}
