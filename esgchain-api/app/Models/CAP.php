<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class CAP extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'caps';

    protected $fillable = [
        'supplier_id', 'saq_id', 'source_type', 'source_id',
        'triggered_by_axis', 'auto_generated',
        'title', 'description', 'status', 'priority',
        'due_date', 'assigned_to', 'created_by',
    ];

    protected $casts = [
        'due_date'       => 'date',
        'source_type'    => 'string',
        'auto_generated' => 'boolean',
    ];

    protected static function booted(): void
    {
        // 自動更新逾期狀態
        static::retrieved(function (self $cap) {
            if ($cap->due_date && $cap->due_date->isPast() && in_array($cap->status, ['open', 'in_progress'])) {
                $cap->updateQuietly(['status' => 'overdue']);
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function saq(): BelongsTo
    {
        return $this->belongsTo(SAQ::class, 'saq_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(CAPFinding::class, 'cap_id');
    }

    public function complianceDoc(): BelongsTo
    {
        return $this->belongsTo(SupplierComplianceDoc::class, 'source_id');
    }
}
