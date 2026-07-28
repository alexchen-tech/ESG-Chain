<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CAPAttachment extends Model
{
    use HasUuids;

    protected $table = 'cap_attachments';

    protected $fillable = [
        'cap_id', 'cap_update_id', 'file_path', 'file_name', 'uploaded_by',
    ];

    public function cap(): BelongsTo
    {
        return $this->belongsTo(CAP::class, 'cap_id');
    }

    public function capUpdate(): BelongsTo
    {
        return $this->belongsTo(CAPUpdate::class, 'cap_update_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
