<?php

namespace App\Services\TradeGoods;

use App\Models\TradeGood;
use App\Models\SalesProduct;
use Illuminate\Support\Facades\Log;
use App\Models\TradeGoodSupplierEmission;
use Illuminate\Support\Collection;

class TradeGoodService
{
    private const STATUS_PRIORITY = [
        'expired'       => 5,
        'missing'       => 4,
        'expiring_soon' => 3,
        'pending'       => 2,
        'valid'         => 1,
        'unconfigured'  => 0,
    ];

    public function getList(): array
    {
        $goods = SalesProduct::with([
            'customer:id,name,code',
            'materialGroup',
            'tradeGoodSuppliers.materialGroup',
            'tradeGoodSuppliers.supplier.complianceDocs',
            'productionBatches:id,sales_product_id,erp_batch_no,production_date',
        ])->orderBy('created_at', 'desc')->get();

        return $goods->map(fn($g) => $this->summarize($g))->values()->toArray();
    }

    public function summarize(SalesProduct $good): array
    {
        $tgs = $good->tradeGoodSuppliers ?? collect();

        $isEudr = $tgs->contains(fn($tgs) =>
            in_array('EUDR_DDS', $tgs->materialGroup?->required_doc_types ?? [])
        );

        $upstreamStatus = $this->calcUpstreamStatus($tgs);

        return [
            'id'                      => $good->id,
            'name'                    => $good->name,
            'product_code'            => $good->product_code,
            'model_no'                => $good->model_no,
            // 生產批號屬批次實體（一對多）；此處僅供列表查詢用的批號集合
            'batch_nos'               => $good->relationLoaded('productionBatches')
                ? $good->productionBatches->pluck('erp_batch_no')->filter()->values()->all()
                : [],
            'hs_code'                 => $good->hs_code,
            'unit'                    => $good->unit,
            'unit_price'              => $good->unit_price,
            'currency'                => $good->currency ?? 'USD',
            'embedded_emissions'      => $good->embedded_emissions,
            'emissions_source'        => $good->emissions_source,
            'emissions_updated_at'    => $good->emissions_updated_at?->toISOString(),
            'is_cbam_applicable'      => $good->is_cbam_applicable,
            'cbam_category'           => $good->cbam_category,
            'is_eudr_applicable'      => $isEudr,
            'upstream_compliance_status' => $upstreamStatus,
            'supplier_count'          => $tgs->count(),
            'description'             => $good->description,
            'customer_id'             => $good->customer_id,
            'customer_name'           => $good->customer?->name,
            'applicable_regulations'  => $good->applicable_regulations ?? [],
            'inferred_regulations'    => $good->inferred_regulations ?? [],
        ];
    }

    public function getUpstreamCompliance(SalesProduct $good): array
    {
        $tgs = $good->tradeGoodSuppliers()->with([
            'supplier.complianceDocs',
            'materialGroup',
        ])->get();

        $items = $tgs->map(function ($tg) {
            $requiredTypes = $tg->materialGroup?->required_doc_types ?? [];
            $docs          = $tg->supplier?->complianceDocs ?? collect();
            $docStatuses   = [];

            foreach ($requiredTypes as $dt) {
                $doc = $docs->firstWhere('doc_type', $dt);
                $docStatuses[] = [
                    'doc_type'   => $dt,
                    'status'     => $doc?->status ?? 'missing',
                    'expires_at' => $doc?->expires_at?->toDateString(),
                ];
            }

            $worst = $this->worstStatus(collect($docStatuses)->pluck('status'));

            return [
                'id'              => $tg->id,
                'supplier_id'     => $tg->supplier_id,
                'supplier_name'   => $tg->supplier?->name,
                'material_group'  => $tg->materialGroup?->name,
                'notes'           => $tg->notes,
                'status'          => empty($requiredTypes) ? 'unconfigured' : $worst,
                'doc_statuses'    => $docStatuses,
            ];
        });

        return $items->values()->toArray();
    }

    public function confirmEmissions(TradeGoodSupplierEmission $emission): void
    {
        $emission->update(['confirmed_at' => now()]);

        $emission->tradeGood->update([
            'embedded_emissions' => $emission->emissions_value,
        ]);

        Log::info('trade_good.confirm_emissions', [
            'trade_good_id'   => $emission->trade_good_id,
            'supplier_id'     => $emission->supplier_id,
            'emissions_value' => $emission->emissions_value,
        ]);
    }

    private function calcUpstreamStatus(Collection $tgs): string
    {
        if ($tgs->isEmpty()) return 'unconfigured';

        $worst = 'valid';
        foreach ($tgs as $tg) {
            $requiredTypes = $tg->materialGroup?->required_doc_types ?? [];
            if (empty($requiredTypes)) continue;

            $docs = $tg->supplier?->complianceDocs ?? collect();
            foreach ($requiredTypes as $dt) {
                $doc    = $docs->firstWhere('doc_type', $dt);
                $status = $doc?->status ?? 'missing';
                if (($this->STATUS_PRIORITY[$status] ?? 0) > ($this->STATUS_PRIORITY[$worst] ?? 0)) {
                    $worst = $status;
                }
            }
        }
        return $worst;
    }

    private function worstStatus(Collection $statuses): string
    {
        return $statuses->reduce(function ($carry, $s) {
            return ($this->STATUS_PRIORITY[$s] ?? 0) > ($this->STATUS_PRIORITY[$carry] ?? 0) ? $s : $carry;
        }, 'valid');
    }
}
