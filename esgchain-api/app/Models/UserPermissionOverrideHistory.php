<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 個人權限覆寫稽核紀錄（僅作稽核記錄用，不作為權限判斷的資料來源，見
 * openspec/changes/crud-permission-granularity/design.md Decision 2）。
 */
class UserPermissionOverrideHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['user_id', 'permission', 'action', 'granted_by'];

    protected $casts = [
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
