<?php

namespace App\Services\ProductionBatch;

use App\Models\ProductionBatch;
use App\Models\SalesProduct;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProductionBatchService
{
    /**
     * Upsert a production batch from Webhook or CSV payload.
     * Matches erp_product_code → sales_product_id
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

        // 批號↔商品對應：以 ERP 產品編碼比對 SalesProduct.product_code（唯一產品主檔）
        $salesProductId = null;
        if (!empty($data['erp_product_code'])) {
            $matches = SalesProduct::where('product_code', $data['erp_product_code'])->get();
            if ($matches->count() === 1) {
                $salesProductId = $matches->first()->id;
            } elseif ($matches->count() > 1) {
                $salesProductId = $matches->first()->id;
                Log::warning('ProductionBatch: multiple sales products matched', [
                    'erp_product_code' => $data['erp_product_code'],
                    'count'            => $matches->count(),
                ]);
            }
        }

        $batch = ProductionBatch::updateOrCreate(
            ['erp_batch_no' => $data['erp_batch_no']],
            array_filter([
                'erp_order_no'                => $data['erp_order_no'] ?? null,
                'sales_product_id'            => $salesProductId,
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
            'rawMaterialOrigins',
        ]);

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        // 匹配 = 已連結產品（sales_product_id 直連，唯一產品主檔）
        if (!empty($filters['matched_status'])) {
            if ($filters['matched_status'] === 'matched') {
                $query->whereNotNull('sales_product_id');
            } elseif ($filters['matched_status'] === 'unmatched') {
                $query->whereNull('sales_product_id');
            }
        }

        return $query->orderByDesc('production_date')->orderByDesc('created_at')->get();
    }
}
