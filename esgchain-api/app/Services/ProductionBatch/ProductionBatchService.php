<?php

namespace App\Services\ProductionBatch;

use App\Models\BuyerProductTradeGood;
use App\Models\ProductionBatch;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProductionBatchService
{
    /**
     * Upsert a production batch from Webhook or CSV payload.
     * Matches erp_product_code → buyer_product_trade_good_id
     * Matches supplier_code → supplier_id
     */
    public function upsertFromPayload(array $data): ProductionBatch
    {
        $supplierId = null;
        if (!empty($data['supplier_code'])) {
            $supplier = Supplier::where('code', $data['supplier_code'])->first();
            $supplierId = $supplier?->id;
        }
        if (!$supplierId && !empty($data['supplier_id'])) {
            $supplierId = $data['supplier_id'];
        }

        $exportLinkId = null;
        if (!empty($data['erp_product_code'])) {
            $matches = BuyerProductTradeGood::where('erp_product_code', $data['erp_product_code'])->get();
            if ($matches->count() === 1) {
                $exportLinkId = $matches->first()->id;
            } elseif ($matches->count() > 1) {
                $exportLinkId = $matches->first()->id;
                Log::warning('ProductionBatch: multiple export links matched', [
                    'erp_product_code' => $data['erp_product_code'],
                    'count'            => $matches->count(),
                ]);
            }
        }

        $batch = ProductionBatch::updateOrCreate(
            ['erp_batch_no' => $data['erp_batch_no']],
            array_filter([
                'erp_order_no'                => $data['erp_order_no'] ?? null,
                'buyer_product_trade_good_id' => $exportLinkId,
                'supplier_id'                 => $supplierId,
                'production_date'             => $data['production_date'] ?? null,
                'quantity'                    => $data['quantity'] ?? 0,
                'unit'                        => $data['unit'] ?? 'pcs',
                'lot_pcf'                     => $data['lot_pcf'] ?? null,
                'lot_pcf_source'              => $data['lot_pcf_source'] ?? null,
                'source'                      => $data['source'] ?? 'manual',
                'erp_synced_at'               => $data['erp_synced_at'] ?? now(),
            ], fn($v) => $v !== null)
        );

        return $batch;
    }

    /**
     * Import multiple rows from CSV. Returns { imported, errors }.
     */
    public function importFromCsv(array $rows): array
    {
        $imported = 0;
        $errors   = [];

        foreach ($rows as $index => $row) {
            $lineNo = $index + 2; // 1-indexed + header row
            if (empty($row['erp_batch_no'])) {
                $errors[] = "第 {$lineNo} 行：erp_batch_no 為必填";
                continue;
            }
            if (empty($row['supplier_code']) && empty($row['supplier_id'])) {
                $errors[] = "第 {$lineNo} 行（{$row['erp_batch_no']}）：supplier_code 為必填";
                continue;
            }

            try {
                $this->upsertFromPayload(array_merge($row, ['source' => 'csv']));
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "第 {$lineNo} 行（{$row['erp_batch_no']}）：{$e->getMessage()}";
            }
        }

        return compact('imported', 'errors');
    }

    /**
     * List production batches with optional filters.
     * Supported: matched_status (matched|unmatched), supplier_id
     */
    public function list(array $filters = []): Collection
    {
        $query = ProductionBatch::with([
            'supplier:id,name,code',
            'salesProduct:id,name,product_code,model_no',
            'exportLink.buyerProduct:id,name,product_code',
            'exportLink.tradeGood:id,name,product_code',
            'rawMaterialOrigins',
        ]);

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        // 匹配 = 已連結產品（sales_product_id 直連）或舊出口連結（buyer_product_trade_good_id）
        if (!empty($filters['matched_status'])) {
            if ($filters['matched_status'] === 'matched') {
                $query->where(fn($q) => $q->whereNotNull('sales_product_id')->orWhereNotNull('buyer_product_trade_good_id'));
            } elseif ($filters['matched_status'] === 'unmatched') {
                $query->whereNull('sales_product_id')->whereNull('buyer_product_trade_good_id');
            }
        }

        return $query->orderByDesc('production_date')->orderByDesc('created_at')->get();
    }
}
