<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentSeriesWeight extends Model
{
    use HasUuids;

    protected $table = 'assessment_series_weights';
    public $timestamps = false;

    protected $fillable = ['series_id', 'source_template_question_id', 'weight'];

    protected $casts = ['weight' => 'float'];

    public function series(): BelongsTo
    {
        return $this->belongsTo(AssessmentSeries::class, 'series_id');
    }
}
