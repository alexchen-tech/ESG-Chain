<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PcfTask extends Model
{
    use HasUuids;

    protected $fillable = [
        'celery_task_id', 'supplier_ids', 'product_ids',
        'status', 'progress', 'result_count', 'error_message', 'created_by',
    ];

    protected $casts = [
        'supplier_ids' => 'array',
        'product_ids'  => 'array',
        'progress'     => 'integer',
    ];
}
