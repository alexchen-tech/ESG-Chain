<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CAPUpdate extends Model
{
    use HasUuids;

    protected $table = 'cap_updates';

    protected $fillable = [
        'cap_id', 'status', 'notes', 'changed_by',
    ];

    public function cap(): BelongsTo
    {
        return $this->belongsTo(CAP::class, 'cap_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CAPAttachment::class, 'cap_update_id');
    }
}
