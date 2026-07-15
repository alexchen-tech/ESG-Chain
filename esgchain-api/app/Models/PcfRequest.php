<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PcfRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'supplier_id',
        'period_start',
        'period_end',
        'due_date',
        'status',
        'trigger_source',
        'notes',
        'saq_round_id',
        'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'due_date'     => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PcfRequestLine::class);
    }
}
