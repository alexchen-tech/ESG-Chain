<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SAQResponseReview extends Model
{
    use HasUuids;

    protected $table = 'saq_response_reviews';

    protected $fillable = [
        'saq_id',
        'project_question_id',
        'reviewer_id',
        'reviewer_score',
        'reason',
    ];

    protected $casts = [
        'reviewer_score' => 'float',
    ];

    public function saq(): BelongsTo
    {
        return $this->belongsTo(SAQ::class, 'saq_id');
    }

    public function projectQuestion(): BelongsTo
    {
        return $this->belongsTo(ProjectQuestion::class, 'project_question_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
