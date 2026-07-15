<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaqScoreSnapshot extends Model
{
    use HasUuids;

    protected $table = 'saq_score_snapshots';

    protected $fillable = [
        'saq_id',
        'score',
        'grade',
        'score_e',
        'score_s',
        'score_g',
        'scoring_model_id',
        'trigger',
        'triggered_by',
        'scored_at',
    ];

    protected $casts = [
        'score'    => 'float',
        'score_e'  => 'float',
        'score_s'  => 'float',
        'score_g'  => 'float',
        'scored_at' => 'datetime',
    ];

    // Append-only：禁用 delete
    public function delete(): bool
    {
        throw new \LogicException('saq_score_snapshots 為 append-only，不允許刪除');
    }

    public function saq(): BelongsTo
    {
        return $this->belongsTo(SAQ::class, 'saq_id');
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
