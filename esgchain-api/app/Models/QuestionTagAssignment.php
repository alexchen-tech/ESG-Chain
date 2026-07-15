<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionTagAssignment extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $table = 'question_tag_assignments';

    protected $fillable = ['question_id', 'tag_id'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(SAQQuestion::class, 'question_id');
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(QuestionTag::class, 'tag_id');
    }
}
