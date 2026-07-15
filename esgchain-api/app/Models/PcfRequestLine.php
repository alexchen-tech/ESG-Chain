<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PcfRequestLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'pcf_request_id',
        'material_item_id',
        'bom_line_id',
        'material_name',
        'hs_code',
        'submitted_at',
        'fulfilled_emission_id',
        'status',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function pcfRequest(): BelongsTo
    {
        return $this->belongsTo(PcfRequest::class);
    }

    public function bomLine(): BelongsTo
    {
        return $this->belongsTo(ProductBomLine::class, 'bom_line_id');
    }

    public function materialItem(): BelongsTo
    {
        return $this->belongsTo(MaterialItem::class);
    }

    public function fulfilledEmission(): BelongsTo
    {
        return $this->belongsTo(MaterialItemEmission::class, 'fulfilled_emission_id');
    }
}
