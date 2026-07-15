<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchExportReview extends Model
{
    use HasUuids;

    public const MARKETS  = ['EU', 'US', 'UK', 'JP', 'GLOBAL'];
    public const STATUSES = ['pending', 'pass', 'warning', 'fail'];

    protected $fillable = [
        'production_batch_id',
        'market',
        'status',
        'findings',
        'reviewed_at',
    ];

    protected $casts = [
        'findings'    => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class);
    }
}
