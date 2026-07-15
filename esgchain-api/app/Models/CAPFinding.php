<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CAPFinding extends Model
{
    use HasUuids;

    protected $table = 'cap_findings';

    protected $fillable = [
        'cap_id', 'category',
        'framework', 'topic_slug', 'source_score', 'threshold',
        'finding', 'root_cause',
        'corrective_action', 'status', 'target_date',
    ];

    protected $casts = [
        'target_date'  => 'date',
        'source_score' => 'float',
        'threshold'    => 'float',
    ];

    public function cap(): BelongsTo
    {
        return $this->belongsTo(CAP::class, 'cap_id');
    }
}
