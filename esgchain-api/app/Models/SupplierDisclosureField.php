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
        'slug', 'label', 'data_type', 'unit', 'period_type', 'description',
    ];

    public function disclosures(): HasMany
    {
        return $this->hasMany(SupplierDisclosure::class, 'field_slug', 'slug');
    }
}
