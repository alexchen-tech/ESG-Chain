<?php

namespace App\Services\Suppliers;

use App\Models\BomLineSupplier;
use App\Models\MaterialItem;
use App\Models\Supplier;
use Illuminate\Http\UploadedFile;

class SupplierAvlImportService
{
    private const HEADER_MAP = [
        'supplier_code'    => ['supplier_code', 'suppliercode', '廠商代碼', 'vendor_code', 'erp代碼'],
        'supplier_name'    => ['supplier_name', 'suppliername', '廠商名稱', 'vendor_name', '供應商名稱'],
        'country_code'     => ['country_code', 'countrycode', '國家碼', '國家'],
        'tier'             => ['tier', '層級', '供應層級'],
        'material_group_code' => ['material_group_code', 'materialgroupcode', '物料群組代碼', 'material_group'],
        'approved_items'   => ['approved_items', 'approveditems', '核准物料', '物料代碼'],
    ];

    public function importFromCsv(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());
        $content = ltrim($content, "\xEF\xBB\xBF");
        $lines   = preg_split('/\r\n|\r|\n/', trim($content));

        if (count($lines) < 2) {
            throw new \InvalidArgumentException('CSV 檔案格式錯誤或無資料列');
        }

        $rawHeaders = str_getcsv(array_shift($lines));
        $headerMap  = $this->mapHeaders($rawHeaders);

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $values = str_getcsv($line);
            $row    = [];
            foreach (array_keys(self::HEADER_MAP) as $field) {
                $idx        = $headerMap[$field] ?? null;
                $row[$field] = ($idx !== null && isset($values[$idx])) ? trim($values[$idx]) : null;
            }
            if (empty($row['supplier_name'])) continue;
            $rows[] = $row;
        }

        return $this->upsertRows($rows);
    }

    private function mapHeaders(array $rawHeaders): array
    {
        $map = [];
        foreach ($rawHeaders as $idx => $header) {
            $normalized = strtolower(trim(str_replace([' ', '-', '_'], '', $header)));
            foreach (self::HEADER_MAP as $field => $aliases) {
                foreach ($aliases as $alias) {
                    if ($normalized === strtolower(str_replace([' ', '-', '_'], '', $alias))) {
                        $map[$field] = $idx;
                        break 2;
                    }
                }
            }
        }

        if (!isset($map['supplier_code']) || !isset($map['supplier_name'])) {
            throw new \InvalidArgumentException('缺少必要欄位：supplier_code 或 supplier_name');
        }

        return $map;
    }

    private function upsertRows(array $rows): array
    {
        $createdSuppliers  = 0;
        $updatedSuppliers  = 0;
        $createdBomLinks   = 0;
        $warnings          = [];

        foreach ($rows as $row) {
            $supplierCode = $row['supplier_code'] ?? null;
            $supplierName = $row['supplier_name'] ?? null;

            if (!$supplierCode || !$supplierName) {
                $warnings[] = "缺少 supplier_code 或 supplier_name，已略過";
                continue;
            }

            $supplier = Supplier::where('code', $supplierCode)->first();

            if ($supplier) {
                $updates = array_filter([
                    'name'         => $supplierName,
                    'country_code' => $row['country_code'] ? strtoupper(substr($row['country_code'], 0, 2)) : null,
                    'tier'         => is_numeric($row['tier'] ?? null) ? (int) $row['tier'] : null,
                ], fn($v) => $v !== null);

                $supplier->update($updates);
                $updatedSuppliers++;
            } else {
                $supplier = Supplier::create([
                    'name'             => $supplierName,
                    'code'             => $supplierCode,
                    'country_code'     => $row['country_code'] ? strtoupper(substr($row['country_code'], 0, 2)) : null,
                    'tier'             => is_numeric($row['tier'] ?? null) ? (int) $row['tier'] : 1,
                    'status'           => 'inactive',
                    'onboarding_stage' => 'potential',
                    'profile_completed' => false,
                ]);
                $createdSuppliers++;
            }

            // 處理 approved_items → BomLineSupplier
            if (!empty($row['approved_items'])) {
                $itemCodes = array_filter(array_map('trim', explode(',', $row['approved_items'])));
                foreach ($itemCodes as $itemCode) {
                    $materialItem = MaterialItem::where('item_code', $itemCode)->first();
                    if (!$materialItem) {
                        $warnings[] = "物料代碼 '{$itemCode}' 在 material_items 中不存在，已略過";
                        continue;
                    }

                    // 透過 material_item_id 找到相關 bom_lines
                    $bomLines = \App\Models\ProductBomLine::where('material_item_id', $materialItem->id)->get();
                    foreach ($bomLines as $bomLine) {
                        $exists = BomLineSupplier::where('bom_line_id', $bomLine->id)
                            ->where('supplier_id', $supplier->id)
                            ->exists();
                        if (!$exists) {
                            BomLineSupplier::create([
                                'bom_line_id' => $bomLine->id,
                                'supplier_id' => $supplier->id,
                                'role'        => 'primary',
                                'source'      => 'erp_designated',
                                'sort_order'  => 0,
                            ]);
                            $createdBomLinks++;
                        }
                    }
                }
            }
        }

        return [
            'created_suppliers' => $createdSuppliers,
            'updated_suppliers' => $updatedSuppliers,
            'created_bom_links' => $createdBomLinks,
            'warnings'          => $warnings,
        ];
    }
}
