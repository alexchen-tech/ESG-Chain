<?php

namespace App\Services\ProductionBatch;

use App\Models\ProductionBatch;
use App\Models\SalesProduct;
use App\Models\SupplierComplianceDoc;
use App\Services\Compliance\ProductUpstreamResolver;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * 跨生產批號的出口審查清單查詢：以 ProductionBatch 為查詢主體，
 * 確保完全沒有 BatchExportReview 記錄的批號（最需要被巡查的「未審查」對象）
 * 一併出現在清單中，不會因為只查 batch_export_reviews 表而被漏掉。
 */
class ExportReviewQueueService
{
    public function __construct(private readonly ProductUpstreamResolver $upstream) {}

    /**
     * Supported filters: market, status（pending|pass|warning|fail|unreviewed）,
     * production_date_from, production_date_to, per_page
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $market = $filters['market'] ?? null;

        $query = ProductionBatch::with([
            'supplier:id,name,code',
            'salesProduct:id,name,product_code,model_no',
            'salesProduct.bomLines.materialGroup',
            'salesProduct.bomLines.materialItem.materialGroup',
            'salesProduct.bomLines.bomLineSuppliers',
            'salesProduct.bomLines.materialItem.approvedSuppliers',
            'rawMaterialOrigins',
            'exportReviews' => function ($q) use ($market) {
                if ($market) {
                    $q->where('market', $market);
                }
                $q->orderByDesc('created_at');
            },
        ])->orderByDesc('production_date');

        if (!empty($filters['production_date_from'])) {
            $query->where('production_date', '>=', $filters['production_date_from']);
        }
        if (!empty($filters['production_date_to'])) {
            $query->where('production_date', '<=', $filters['production_date_to']);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'unreviewed') {
                $query->whereDoesntHave('exportReviews', function ($q) use ($market) {
                    if ($market) {
                        $q->where('market', $market);
                    }
                });
            } else {
                $query->whereHas('exportReviews', function ($q) use ($market, $filters) {
                    if ($market) {
                        $q->where('market', $market);
                    }
                    $q->where('status', $filters['status']);
                });
            }
        } elseif ($market) {
            // 未指定 status，但有指定 market：仍要能看到該市場「未審查」的批號，
            // 因此不加 whereHas 條件，讓 LEFT JOIN 語意（eager load）自然涵蓋兩種狀態
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        $paginator = $query->paginate($perPage);

        // 每個批號的相關供應商 ID（僅本頁，最多 per_page 筆，非全表）
        $supplierIdsByBatch = $paginator->getCollection()->mapWithKeys(function (ProductionBatch $batch) {
            $product = $batch->salesProduct;
            $ids = $product ? $this->upstream->batchSupplierIds($batch, $product) : [];
            return [$batch->id => $ids];
        });

        // 本頁所有候選供應商的合規文件最後更新時間，一次查完避免逐批號查詢
        $allSupplierIds = $supplierIdsByBatch->flatten()->unique()->values();
        $lastDocUpdateBySupplier = SupplierComplianceDoc::whereIn('supplier_id', $allSupplierIds)
            ->selectRaw('supplier_id, MAX(updated_at) as last_updated_at')
            ->groupBy('supplier_id')
            ->pluck('last_updated_at', 'supplier_id');

        $paginator->getCollection()->transform(
            fn (ProductionBatch $batch) => $this->format($batch, $market, $supplierIdsByBatch->get($batch->id, []), $lastDocUpdateBySupplier)
        );

        return $paginator;
    }

    private function format(ProductionBatch $batch, ?string $market, array $supplierIds, $lastDocUpdateBySupplier): array
    {
        $latestReview = $batch->exportReviews->first();

        $possiblyStale = false;
        if ($latestReview?->reviewed_at) {
            foreach ($supplierIds as $supplierId) {
                $lastUpdate = $lastDocUpdateBySupplier->get($supplierId);
                if ($lastUpdate && $lastUpdate > $latestReview->reviewed_at) {
                    $possiblyStale = true;
                    break;
                }
            }
        }

        return [
            'id'                => $batch->id,
            'erp_batch_no'      => $batch->erp_batch_no,
            'erp_order_no'      => $batch->erp_order_no,
            'production_date'   => $batch->production_date?->toDateString(),
            'quantity'          => $batch->quantity,
            'unit'              => $batch->unit,
            'supplier_name'     => $batch->supplier?->name,
            'supplier_code'     => $batch->supplier?->code,
            'sales_product_id'  => $batch->sales_product_id,
            'product_name'      => $batch->salesProduct?->name,
            'product_code'      => $batch->salesProduct?->product_code,
            // 未篩選市場時僅顯示「最新建立」那一筆審查的市場，不代表該批次只審查過
            // 這一個市場——若批次同時有多個市場審查紀錄，其餘紀錄不會反映在這個欄位，
            // 僅供單一狀態摘要用途，非完整審查市場清單。
            'market'            => $latestReview?->market ?? $market,
            'status'            => $latestReview?->status ?? 'unreviewed',
            'reviewed_at'       => $latestReview?->reviewed_at?->toISOString(),
            'possibly_stale'    => $possiblyStale,
        ];
    }
}
