<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomLineSupplier extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'bom_line_id',
        'supplier_id',
        'role',
        'source',
        'sort_order',
    ];

    public function bomLine(): BelongsTo
    {
        return $this->belongsTo(ProductBomLine::class, 'bom_line_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
