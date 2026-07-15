<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupplierImport extends Model
{
    use HasUuids;

    protected $table = 'supplier_imports';

    protected $fillable = [
        'batch_id', 'vendor_code', 'vat_number', 'vendor_name',
        'spend_amount', 'country_code', 'material_group', 'primary_email',
        'cleanse_status', 'failure_codes', 'notes', 'erp_vendor_codes',
    ];

    protected $casts = [
        'failure_codes'    => 'array',
        'erp_vendor_codes' => 'array',
        'spend_amount'     => 'float',
    ];
}
