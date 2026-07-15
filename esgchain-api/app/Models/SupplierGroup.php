<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class SupplierGroup extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'description', 'required_doc_types'];

    protected $casts = [
        'required_doc_types' => 'array',
    ];

    private const DOC_TYPE_TO_DOMAIN = [
        'UFLPA_DECLARATION' => 'UFLPA',
        'EUDR_DDS'          => 'EUDR',
        'CMRT'              => 'CMRT',
        'SDS'               => 'SDS',
        'CE_DOC'            => 'CE',
    ];

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class, 'group_id');
    }

    public function inferredMaterialGroups(): array
    {
        return Cache::remember("supplier_group_{$this->id}_inferred", 600, function () {
            $materialGroups = MaterialGroup::whereIn('id',
                TradeGood::whereIn('supplier_id', $this->suppliers()->pluck('id'))
                    ->whereNotNull('material_group_id')
                    ->pluck('material_group_id')
                    ->unique()
            )->get();

            $complianceDomains = $materialGroups
                ->flatMap(fn($mg) => $mg->required_doc_types ?? [])
                ->map(fn($dt) => self::DOC_TYPE_TO_DOMAIN[$dt] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            return [
                'material_groups'   => $materialGroups->map(fn($mg) => [
                    'id'                 => $mg->id,
                    'name'               => $mg->name,
                    'required_doc_types' => $mg->required_doc_types,
                ])->values()->toArray(),
                'compliance_domains' => $complianceDomains,
            ];
        });
    }

    public function clearInferredCache(): void
    {
        Cache::forget("supplier_group_{$this->id}_inferred");
    }
}
