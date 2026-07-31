<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRoleHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['user_id', 'from_roles', 'to_roles', 'changed_by'];

    protected $casts = [
        'from_roles' => 'array',
        'to_roles' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            $model->created_at ??= now();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
