<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class BuyerProduct extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'product_code',
        'description',
        'applicable_regulations',
        'inferred_regulations',
        'latest_pcf_snapshot_id',
    ];

    protected $casts = [
        'applicable_regulations' => 'array',
        'inferred_regulations'   => 'array',
    ];

    public function bomLines(): HasMany
    {
        return $this->hasMany(ProductBomLine::class);
    }

    public function pcfSnapshots(): HasMany
    {
        return $this->hasMany(PcfSnapshot::class);
    }

    public function latestPcfSnapshot(): ?PcfSnapshot
    {
        return $this->pcfSnapshots()->latest('snapshot_at')->first();
    }

    public function exportLinks(): HasMany
    {
        return $this->hasMany(BuyerProductTradeGood::class);
    }

    public function exportedTradeGoods(): HasManyThrough
    {
        return $this->hasManyThrough(TradeGood::class, BuyerProductTradeGood::class, 'buyer_product_id', 'id', 'id', 'trade_good_id');
    }

    public function syncInferredRegulations(): array
    {
        $regulations = $this->bomLines()
            ->whereNotNull('material_group_id')
            ->with('materialGroup')
            ->get()
            ->flatMap(fn($line) => $line->materialGroup?->required_doc_types ?? [])
            ->map(fn($docType) => match ($docType) {
                'UFLPA_DECLARATION' => 'UFLPA',
                'EUDR_DDS'          => 'EUDR',
                'CMRT'              => 'CMRT',
                'SDS'               => 'REACH',
                'CE_DOC'            => 'CE',
                default             => null,
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $this->update(['inferred_regulations' => $regulations]);

        app(\App\Services\ExportLink\ExportLinkSyncService::class)->syncEudrFromRegulations($this);

        return $regulations;
    }
}
