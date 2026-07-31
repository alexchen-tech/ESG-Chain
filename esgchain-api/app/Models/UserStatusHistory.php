<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStatusHistory extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'from_status', 'to_status', 'reason', 'changed_by'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
