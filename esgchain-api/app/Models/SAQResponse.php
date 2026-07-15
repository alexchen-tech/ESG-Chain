<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SAQResponse extends Model
{
    use HasUuids;

    protected $table = 'saq_responses';

    protected $fillable = [
        'saq_id', 'question_id', 'project_question_id',
        'answer', 'answer_options', 'evidence_note', 'raw_score',
        'llm_score', 'llm_score_reason', 'score_confidence',
    ];

    protected $casts = [
        'answer_options' => 'array',
        'raw_score'      => 'float',
        'llm_score'      => 'float',
    ];
}
