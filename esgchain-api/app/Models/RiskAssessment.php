<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAssessment extends Model
{
    use HasUuids;

    protected $fillable = [
        'supplier_id',
        'assessed_at', 'assessed_by', 'notes',
        'source_saq_id', 'source_type', 'source_id',
        'ai_suggestion', 'ai_generated_at',
        'dim_e1', 'dim_e2', 'dim_e3', 'dim_e4', 'dim_e5', 'dim_e6',
        'assessment_version', 'impact_score',
    ];

    protected $casts = [
        'assessed_at'     => 'datetime',
        'ai_generated_at' => 'datetime',
        'impact_score'    => 'integer',
        'dim_e1' => 'float', 'dim_e2' => 'float', 'dim_e3' => 'float',
        'dim_e4' => 'float', 'dim_e5' => 'float', 'dim_e6' => 'float',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // 六維分數（0-100）轉風險等級
    public static function dimToLevel(?float $score): string
    {
        if ($score === null) return 'unknown';

        return match (true) {
            $score >= 70 => 'low',
            $score >= 40 => 'medium',
            default      => 'high',
        };
    }

    public function toRiskSummary(): array
    {
        return [
            'supplier_id'        => $this->supplier_id,
            'assessment_version' => $this->assessment_version ?? 'v3',
            'source_type'        => $this->source_type ?? 'saq',
            'assessed_at'        => $this->assessed_at?->toIso8601String(),
            'six_dims' => [
                'E1' => $this->dim_e1,
                'E2' => $this->dim_e2,
                'E3' => $this->dim_e3,
                'E4' => $this->dim_e4,
                'E5' => $this->dim_e5,
                'E6' => $this->dim_e6,
            ],
        ];
    }
}
