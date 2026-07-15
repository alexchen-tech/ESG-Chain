<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SAQQuestion extends Model
{
    use HasUuids;

    protected $table = 'saq_questions';

    protected $fillable = [
        'template_id', 'question_text',
        'question_type', 'options', 'weight', 'order', 'is_required',
        'sasb_topic_id', 'sasb_metric_code',
        'tags', 'compliance_domains', 'source_bank_question_id',
        'scoring_direction', 'scoring_type', 'option_scores',
        'disclosure_field_slug', 'framework_pillar',
    ];

    protected $casts = [
        'options'            => 'array',
        'tags'               => 'array',
        'compliance_domains' => 'array',
        'option_scores'      => 'array',
        'weight'             => 'float',
        'is_required'        => 'boolean',
    ];

    /**
     * tags 欄位為 L3 slug 字串陣列，每個元素對應 question_tags.slug（l3_topic!='General'）。
     * framework/pillar/weight 全從 question_tags 推導，不存於此欄位。
     *
     * 驗證：每個 slug 必須存在於 question_tags 且為 L3 節點。
     */
    public static function validateTagsStructure(array $slugs): array
    {
        $errors = [];
        foreach ($slugs as $i => $slug) {
            if (!is_string($slug) || $slug === '') {
                $errors[] = "tags[{$i}] 不可為空字串";
                continue;
            }
            $exists = \DB::table('question_tags')
                ->where('slug', $slug)
                ->where('l3_topic', '!=', 'General')
                ->exists();
            if (!$exists) {
                $errors[] = "tags[{$i}] '{$slug}' 不是有效的 L3 slug";
            }
        }
        return $errors;
    }

    /**
     * 從 L3 slug 陣列推導 l1_domain，更新 compliance_domains。
     * 計算方式：取每個 slug 前兩段 dot 對應 L2 節點，再查 L2 節點的 l1_domain。
     */
    public static function syncComplianceDomains(array $slugs): array
    {
        if (empty($slugs)) {
            return [];
        }
        $l2slugs = array_unique(array_map(
            fn($s) => implode('.', array_slice(explode('.', $s), 0, 2)),
            $slugs
        ));
        $domains = \DB::table('question_tags')
            ->whereIn('slug', $l2slugs)
            ->where('l3_topic', 'General')
            ->pluck('l1_domain')
            ->unique()
            ->values()
            ->toArray();
        return $domains;
    }

    public function scopeBank($query)
    {
        return $query->whereNull('template_id');
    }

    public function usageCount(): int
    {
        return static::whereNotNull('template_id')
            ->where('source_bank_question_id', $this->id)
            ->count();
    }

    public function tagAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QuestionTagAssignment::class, 'question_id');
    }

    public function questionTags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(QuestionTag::class, 'question_tag_assignments', 'question_id', 'tag_id');
    }

    public function sasbTopic(): BelongsTo
    {
        return $this->belongsTo(SasbDisclosureTopic::class, 'sasb_topic_id');
    }
}
