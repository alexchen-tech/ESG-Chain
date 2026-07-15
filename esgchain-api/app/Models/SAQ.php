<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\SupplierDisclosure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class SAQ extends Model
{
    use HasUuids;

    protected $table = 'saqs';

    // 七狀態（spec v2.1.0）
    const NON_EDITABLE_STATUSES = ['submitted', 'under_review', 'completed', 'reviewed'];

    protected $fillable = [
        'project_id', 'supplier_id', 'template_id',
        'status', 'score', 'grade', 'scoring_job_id',
        'score_e', 'score_s', 'score_g', 'category_scores', 'scoring_model_id',
        'final_score', 'final_grade', 'disputed_at',
        'sent_at', 'submitted_at', 'reviewed_at',
        'review_started_at', 'reviewed_by_id',
        'active_modules', 'regulations_snapshot',
        'dim_e1', 'dim_e2', 'dim_e3', 'dim_e4', 'dim_e5', 'dim_e6',
    ];

    protected $appends = ['is_editable'];

    protected $casts = [
        'score'       => 'float',
        'score_e'         => 'float',
        'score_s'         => 'float',
        'score_g'         => 'float',
        'category_scores'      => 'array',
        'active_modules'       => 'array',
        'regulations_snapshot' => 'array',
        'final_score'     => 'float',
        'dim_e1' => 'float', 'dim_e2' => 'float', 'dim_e3' => 'float',
        'dim_e4' => 'float', 'dim_e5' => 'float', 'dim_e6' => 'float',
        'sent_at'          => 'datetime',
        'submitted_at'     => 'datetime',
        'reviewed_at'      => 'datetime',
        'review_started_at' => 'datetime',
        'disputed_at'      => 'datetime',
    ];

    public function getIsEditableAttribute(): bool
    {
        return !in_array($this->status, self::NON_EDITABLE_STATUSES);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SaqProject::class, 'project_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SAQTemplate::class, 'template_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SAQResponse::class, 'saq_id');
    }

    public function reviewHistories(): HasMany
    {
        return $this->hasMany(SaqReviewHistory::class, 'saq_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function responseReviews(): HasMany
    {
        return $this->hasMany(SAQResponseReview::class, 'saq_id');
    }

    public function scoreSnapshots(): HasMany
    {
        return $this->hasMany(SaqScoreSnapshot::class, 'saq_id')->orderByDesc('scored_at');
    }

    public function disclosures(): HasMany
    {
        return $this->hasMany(SupplierDisclosure::class, 'source_saq_id');
    }

    public function projectQuestionsViaProject(): HasManyThrough
    {
        return $this->hasManyThrough(
            ProjectQuestion::class,
            SaqProject::class,
            'id',          // SaqProject foreign key on saq_projects
            'project_id',  // ProjectQuestion foreign key
            'project_id',  // SAQ local key
            'id'           // SaqProject local key
        );
    }
}
