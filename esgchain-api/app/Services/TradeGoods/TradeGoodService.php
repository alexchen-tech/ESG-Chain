<?php

namespace App\Services\TradeGoods;

use App\Models\TradeGood;
use App\Models\SalesProduct;
use App\Models\SupplierComplianceDoc;
use App\Services\Compliance\ProductUpstreamResolver;
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

    public function __construct(private readonly ProductUpstreamResolver $upstream) {}

    public function getList(): array
    {
        $goods = SalesProduct::with([
            'customer:id,name,code',
            'materialGroup',
            'productionBatches:id,sales_product_id,erp_batch_no,production_date',
            'bomLines.materialGroup',
            'bomLines.materialItem.materialGroup',
            'bomLines.bomLineSuppliers',
            'bomLines.materialItem.approvedSuppliers',
        ])->orderBy('created_at', 'desc')->get();

        return $goods->map(fn($g) => $this->summarize($g))->values()->toArray();
    }

    public function summarize(SalesProduct $good): array
    {
        // EUDR 適用判斷改依 BOM 行的物料群組（與「AI 推算法規」同一資料源），
        // 不再依賴 tradeGoodSuppliers——後者常因未維護而空白，會讓徽章誤判為「不適用」。
        $isEudr = $this->isEudrApplicable($good);

        $supplierIds = $this->upstream->supplierIds($good);
        $upstreamStatus = $this->calcUpstreamStatus($good, $supplierIds);

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
            'dpp_category'            => $good->dpp_category,
            'is_eudr_applicable'      => $isEudr,
            'upstream_compliance_status' => $upstreamStatus,
            'supplier_count'          => count($supplierIds),
            'description'             => $good->description,
            'customer_id'             => $good->customer_id,
            'customer_name'           => $good->customer?->name,
            'applicable_regulations'  => $good->applicable_regulations ?? [],
            'inferred_regulations'    => $good->inferred_regulations ?? [],
        ];
    }

    public function getUpstreamCompliance(SalesProduct $good): array
    {
        $summaries = $this->upstream->supplierSummaries($good);

        if ($summaries->isEmpty()) {
            return [];
        }

        $supplierIds = $summaries->pluck('supplier_id')->values()->all();
        $suppliersById = \App\Models\Supplier::whereIn('id', $supplierIds)
            ->with('complianceDocs')
            ->get()
            ->keyBy('id');

        $items = $summaries->map(function ($summary) use ($suppliersById) {
            $supplier      = $suppliersById->get($summary['supplier_id']);
            $requiredTypes = $summary['required_doc_types'];
            $docs          = $supplier?->complianceDocs ?? collect();
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
                'id'                     => $summary['supplier_id'],
                'supplier_id'            => $summary['supplier_id'],
                'supplier_name'          => $supplier?->name,
                'material_group'         => $summary['material_group'],
                'supplier_facility_id'   => null,
                'supplier_facility_name' => $summary['supplier_facility_name'],
                'facility_type'          => $summary['facility_type'],
                'notes'                  => null,
                'status'                 => empty($requiredTypes) ? 'unconfigured' : $worst,
                'doc_statuses'           => $docStatuses,
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

    /**
     * EUDR 適用判斷：BOM 行的「有效物料群組」（materialItem->materialGroup 優先，
     * 無則 fallback BomLine 自身 materialGroup）required_doc_types 含 EUDR_DDS 即適用。
     * 與 SalesProduct::syncInferredRegulations() 同一套邏輯，確保徽章與 AI 推算法規清單一致。
     */
    private function isEudrApplicable(SalesProduct $good): bool
    {
        $lines = $good->relationLoaded('bomLines')
            ? $good->bomLines
            : $good->bomLines()->with(['materialGroup', 'materialItem.materialGroup'])->get();

        return $lines->contains(function ($line) {
            $group = $line->materialItem?->materialGroup ?? $line->materialGroup;
            return in_array('EUDR_DDS', $group?->required_doc_types ?? [], true);
        });
    }

    private function calcUpstreamStatus(SalesProduct $good, array $supplierIds): string
    {
        if (empty($supplierIds)) return 'unconfigured';

        $summaries = $this->upstream->supplierSummaries($good);
        $suppliersById = \App\Models\Supplier::whereIn('id', $supplierIds)
            ->with('complianceDocs')
            ->get()
            ->keyBy('id');

        $worst = 'valid';
        foreach ($summaries as $summary) {
            $requiredTypes = $summary['required_doc_types'];
            if (empty($requiredTypes)) continue;

            $docs = $suppliersById->get($summary['supplier_id'])?->complianceDocs ?? collect();
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
