<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectQuestion extends Model
{
    use HasUuids;

    protected $table = 'project_questions';

    protected $fillable = [
        'project_id', 'order', 'question_text', 'question_type',
        'options', 'weight', 'is_required',
        'sasb_topic_id', 'sasb_metric_code', 'tags',
        'source_bank_question_id', 'source_template_question_id',
        'scoring_direction', 'scoring_type', 'option_scores',
        'is_sasb_required',
    ];

    protected $casts = [
        'options'          => 'array',
        'tags'             => 'array',
        'option_scores'    => 'array',
        'weight'           => 'float',
        'is_required'      => 'boolean',
        'is_sasb_required' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SaqProject::class, 'project_id');
    }

    public function bankQuestion(): BelongsTo
    {
        return $this->belongsTo(SAQQuestion::class, 'source_bank_question_id');
    }

    public function scopeByProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId)->orderBy('order');
    }
}
