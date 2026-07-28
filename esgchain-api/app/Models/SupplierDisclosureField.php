<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierDisclosureField extends Model
{
    protected $primaryKey = 'slug';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'slug', 'label', 'data_type', 'unit', 'options', 'period_type', 'description',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    public function disclosures(): HasMany
    {
        return $this->hasMany(SupplierDisclosure::class, 'field_slug', 'slug');
    }
}
