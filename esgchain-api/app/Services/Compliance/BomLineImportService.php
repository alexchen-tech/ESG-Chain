<?php

namespace App\Services\Compliance;

use App\Jobs\ChemicalComplianceScanJob;
use App\Jobs\PcfEmissionGapScanJob;
use App\Models\BomLineSupplier;
use App\Models\MaterialItem;
use App\Models\ProductBomLine;
use App\Models\SalesProduct;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use League\Csv\Reader;

class BomLineImportService
{
    /**
     * 從 JSON 陣列匯入 BOM 明細（冪等 upsert）
     * @param string $erpSyncSource 同步來源 (csv/webhook/scheduled/manual)
     */
    public function importFromArray(SalesProduct $product, array $rows, string $erpSyncSource = 'manual'): array
    {
        $created        = 0;
        $updated        = 0;
        $linkedSuppliers = 0;
        $warnings       = [];
        $now            = Carbon::now();

        foreach ($rows as $row) {
            $erpLineId    = $row['erp_line_id'] ?? null;
            $materialName = $row['material_name'] ?? null;

            if (!$erpLineId || !$materialName) {
                $warnings[] = "缺少必填欄位 erp_line_id 或 material_name，已略過";
                continue;
            }

            // 3.1: 依 material_code 自動 upsert MaterialItem
            $materialItemId = null;
            if (!empty($row['material_code'])) {
                $materialItem = MaterialItem::updateOrCreate(
                    ['item_code' => $row['material_code']],
                    array_filter([
                        'name'    => $materialName,
                        'hs_code' => $row['hs_code'] ?? null,
                    ], fn($v) => $v !== null),
                );
                $materialItemId = $materialItem->id;
            }

            // ERP 控制欄位（每次匯入都更新）
            $erpFields = array_filter([
                'material_name'    => $materialName,
                'hs_code'          => $row['hs_code'] ?? null,
                'quantity'         => isset($row['quantity']) ? (float) $row['quantity'] : null,
                'unit'             => $row['unit'] ?? null,
                'unit_price'       => isset($row['unit_price']) ? (float) $row['unit_price'] : null,
                'currency'         => $row['currency'] ?? null,
                // 3.3: erp_sync_source & erp_synced_at
                'erp_sync_source'  => $erpSyncSource,
                'erp_synced_at'    => $now,
                'material_item_id' => $materialItemId,
            ], fn($v) => $v !== null);

            // 解析 supplier_code（舊欄位，向下相容）
            $supplierId     = null;
            $supplierSource = null;
            if (!empty($row['supplier_code'])) {
                $supplier = Supplier::where('code', $row['supplier_code'])->first();
                if ($supplier) {
                    $supplierId     = $supplier->id;
                    $supplierSource = 'erp_designated';
                } else {
                    $warnings[] = "supplier_code '{$row['supplier_code']}' 無法解析，designated_supplier_id 設為 null";
                }
                $erpFields['designated_supplier_id'] = $supplierId;
                $erpFields['supplier_source']        = $supplierSource;
            }

            // 查找現有記錄
            $existing = ProductBomLine::withTrashed()
                ->where('sales_product_id', $product->id)
                ->where('erp_line_id', $erpLineId)
                ->first();

            if ($existing) {
                // 保護 manual 標註欄位
                $updateFields = $erpFields;

                if ($existing->material_group_source === 'manual') {
                    unset($updateFields['material_group_id'], $updateFields['material_group_source']);
                } else {
                    if (isset($updateFields['material_group_id'])) {
                        $updateFields['material_group_source'] = 'erp_imported';
                    }
                }

                $existing->update($updateFields);
                $bomLine = $existing;
                $updated++;
            } else {
                $createFields = array_merge($erpFields, [
                    'sales_product_id'      => $product->id,
                    'erp_line_id'           => $erpLineId,
                    'material_group_source' => !empty($erpFields['material_group_id']) ? 'erp_imported' : null,
                ]);
                $bomLine = ProductBomLine::create($createFields);
                $created++;
            }

            // 建立 BomLineSupplier（primary_supplier_code / alternate_supplier_code）
            $supplierLinks = [
                'primary'   => $row['primary_supplier_code'] ?? null,
                'alternate' => $row['alternate_supplier_code'] ?? null,
            ];
            foreach ($supplierLinks as $role => $code) {
                if (empty($code)) continue;
                $linkedSupplier = Supplier::where('code', $code)->first();
                if (!$linkedSupplier) {
                    $warnings[] = "{$role}_supplier_code '{$code}' 無法解析，BomLineSupplier 關聯已略過";
                    continue;
                }
                $exists = BomLineSupplier::where('bom_line_id', $bomLine->id)
                    ->where('supplier_id', $linkedSupplier->id)
                    ->exists();
                if (!$exists) {
                    BomLineSupplier::create([
                        'bom_line_id' => $bomLine->id,
                        'supplier_id' => $linkedSupplier->id,
                        'role'        => $role,
                        'source'      => 'erp_designated',
                        'sort_order'  => $role === 'primary' ? 0 : 1,
                    ]);
                    $linkedSuppliers++;
                }
            }
        }

        // 3.2: 匯入完成後非同步觸發碳排缺口掃描及化學合規掃描
        if ($created > 0 || $updated > 0) {
            PcfEmissionGapScanJob::dispatch($product->id, null, 'system_bom_import');

            // 觸發所有受影響物料的化學合規掃描
            $affectedMaterialIds = collect($rows ?? [])
                ->pluck('material_code')
                ->unique()
                ->filter()
                ->map(fn ($code) => \App\Models\MaterialItem::where('item_code', $code)->value('id'))
                ->filter()
                ->values();

            foreach ($affectedMaterialIds as $materialItemId) {
                ChemicalComplianceScanJob::dispatch($materialItemId);
            }
        }

        return ['created' => $created, 'updated' => $updated, 'linked_suppliers' => $linkedSuppliers, 'warnings' => $warnings];
    }

    /**
     * 從 CSV 檔案匯入 BOM 明細
     */
    public function importFromCsv(SalesProduct $product, UploadedFile $file): array
    {
        $csv = Reader::createFromPath($file->getRealPath(), 'r');
        $csv->setHeaderOffset(0);

        $requiredHeaders = ['erp_line_id', 'material_name'];
        $headers         = $csv->getHeader();

        $missing = array_diff($requiredHeaders, $headers);
        if (!empty($missing)) {
            abort(422, '缺少必填欄位：' . implode(', ', $missing));
        }

        $rows = [];
        foreach ($csv->getRecords() as $record) {
            $rows[] = array_filter($record, fn($v) => $v !== '');
        }

        return $this->importFromArray($product, $rows, 'csv');
    }
}
