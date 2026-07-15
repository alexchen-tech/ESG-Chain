<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaqProject extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'template_id', 'status', 'closed_at', 'start_date', 'due_date', 'description', 'domain',
        'series_id', 'template_ref_id', 'template_ref_version', 'is_comparable', 'template_version',
    ];

    protected $casts = ['start_date' => 'date', 'due_date' => 'date', 'closed_at' => 'datetime', 'is_comparable' => 'boolean'];

    private const STATUS_TRANSITIONS = [
        'draft'  => ['active'],
        'active' => ['closed'],
        'closed' => [],
    ];

    public function transitionStatus(string $new): void
    {
        $allowed = self::STATUS_TRANSITIONS[$this->status] ?? [];
        if (!in_array($new, $allowed, true)) {
            abort(422, "不允許從 {$this->status} 轉換至 {$new}");
        }
        $this->update([
            'status'    => $new,
            'closed_at' => $new === 'closed' ? now() : $this->closed_at,
        ]);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SAQTemplate::class, 'template_id');
    }

    public function saqs(): HasMany
    {
        return $this->hasMany(SAQ::class, 'project_id');
    }

    public function projectQuestions(): HasMany
    {
        return $this->hasMany(ProjectQuestion::class, 'project_id')->orderBy('order');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(AssessmentSeries::class, 'series_id');
    }
}
