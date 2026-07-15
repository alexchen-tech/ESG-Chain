<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialGroup extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'name',
        'group_type',
        'description',
        'hs_code_prefixes',
        'required_doc_types',
        'is_system',
    ];

    protected $casts = [
        'hs_code_prefixes'   => 'array',
        'required_doc_types' => 'array',
        'is_system'          => 'boolean',
    ];

    public function complianceDocs(): HasMany
    {
        return $this->hasMany(SupplierComplianceDoc::class);
    }

    public function tradeGoods(): HasMany
    {
        return $this->hasMany(TradeGood::class);
    }

    public static function findByHsCode(string $hsCode): ?self
    {
        return self::all()->first(function (self $group) use ($hsCode) {
            foreach ($group->hs_code_prefixes as $prefix) {
                if (str_starts_with($hsCode, $prefix)) {
                    return true;
                }
            }
            return false;
        });
    }
}
