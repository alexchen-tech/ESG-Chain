<?php

namespace App\Services\Suppliers;

use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\SupplierImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class SupplierImportService
{
    // 支援中英文 header 對應
    private const HEADER_MAP = [
        'vendor_code'    => ['vendor_code', 'vendorcode', '廠商代碼', 'erp代碼'],
        'vat_number'     => ['vat_number', 'vatnumber', '統編vat', '統編', 'vat', 'taxid', 'tax_id', 'duns'],
        'vendor_name'    => ['vendor_name', 'vendorname', '廠商名稱', '供應商名稱'],
        'spend_amount'   => ['spend_amount', 'spendamount', '年採購額', '採購金額'],
        'country_code'   => ['country_code', 'countrycode', '國家碼', '國家'],
        'material_group' => ['material_group', 'materialgroup', '採購類別', '物料群'],
        'primary_email'  => ['primary_email', 'primaryemail', '主要信箱', 'email', '信箱'],
    ];

    public function parseCsv(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());
        // 處理 BOM
        $content = ltrim($content, "\xEF\xBB\xBF");
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (count($lines) < 2) {
            throw new \InvalidArgumentException('CSV 檔案格式錯誤或無資料列');
        }

        $rawHeaders = str_getcsv(array_shift($lines));
        $headerMap = $this->mapHeaders($rawHeaders);

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $values = str_getcsv($line);
            $row = [];
            foreach (array_keys(self::HEADER_MAP) as $field) {
                $idx = $headerMap[$field] ?? null;
                $row[$field] = ($idx !== null && isset($values[$idx])) ? trim($values[$idx]) : null;
            }
            if (empty($row['vendor_name'])) continue;
            $rows[] = $row;
        }

        return $rows;
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

        $missing = array_diff(array_keys(self::HEADER_MAP), array_keys($map));
        $required = ['vendor_code', 'vat_number', 'vendor_name', 'primary_email'];
        $missingRequired = array_intersect($required, $missing);
        if (!empty($missingRequired)) {
            throw new \InvalidArgumentException('缺少必要欄位：' . implode(', ', $missingRequired));
        }

        return $map;
    }

    public function ingestBatch(array $rows, string $batchId): void
    {
        foreach ($rows as $row) {
            SupplierImport::create([
                'batch_id'       => $batchId,
                'vendor_code'    => $row['vendor_code'] ?: null,
                'vat_number'     => $row['vat_number'] ?: null,
                'vendor_name'    => $row['vendor_name'],
                'spend_amount'   => is_numeric($row['spend_amount']) ? (float) $row['spend_amount'] : null,
                'country_code'   => $row['country_code'] ? strtoupper(substr($row['country_code'], 0, 2)) : null,
                'material_group' => $row['material_group'] ?: null,
                'primary_email'  => $row['primary_email'] ?: null,
                'cleanse_status' => 'staged',
                'erp_vendor_codes' => $row['vendor_code'] ? [$row['vendor_code']] : [],
            ]);
        }
    }

    public function cleanseBatch(string $batchId): array
    {
        $items = SupplierImport::where('batch_id', $batchId)
            ->where('cleanse_status', 'staged')
            ->get();

        $vatGroups = [];   // vat_number → [first_id, [all_vendor_codes]]
        $stats = ['cleansed' => 0, 'rejected' => 0];

        foreach ($items as $item) {
            $failures = [];

            // L1：Email 防呆
            if (!$this->isValidEmail($item->primary_email)) {
                $failures[] = 'email_invalid';
            }

            // 收集 VAT 分組資訊
            if ($item->vat_number) {
                $vatGroups[$item->vat_number][] = $item->id;
            }

            if (!empty($failures)) {
                $item->update([
                    'cleanse_status' => 'rejected',
                    'failure_codes'  => $failures,
                ]);
                $stats['rejected']++;
            }
        }

        // L2：VAT 去重（只處理 L1 通過的）
        $items->load([]); // refresh
        $passedItems = SupplierImport::where('batch_id', $batchId)
            ->where('cleanse_status', 'staged')
            ->get();

        $seenVats = [];
        foreach ($passedItems as $item) {
            $vat = $item->vat_number;

            // 檢查主表是否已有此 VAT
            if ($vat && Supplier::where('vat_number', $vat)->exists()) {
                $item->update([
                    'cleanse_status' => 'rejected',
                    'failure_codes'  => ['vat_exists_in_master'],
                ]);
                $stats['rejected']++;
                continue;
            }

            if ($vat && isset($seenVats[$vat])) {
                // 合併 erp_vendor_codes 到第一筆
                $first = SupplierImport::find($seenVats[$vat]);
                if ($first) {
                    $codes = array_unique(array_merge(
                        $first->erp_vendor_codes ?? [],
                        $item->erp_vendor_codes ?? []
                    ));
                    $first->update(['erp_vendor_codes' => array_values($codes)]);
                }
                $item->update([
                    'cleanse_status' => 'rejected',
                    'failure_codes'  => ['duplicate_vat_merged'],
                ]);
                $stats['rejected']++;
            } else {
                if ($vat) $seenVats[$vat] = $item->id;
                $item->update(['cleanse_status' => 'cleansed']);
                $stats['cleansed']++;
            }
        }

        return $stats;
    }

    public function approveBatch(string $batchId): array
    {
        $items = SupplierImport::where('batch_id', $batchId)
            ->whereIn('cleanse_status', ['cleansed', 'exempt'])
            ->get();

        $approved = 0;
        $skipped  = 0;

        foreach ($items as $item) {
            // 防重複放行（VAT 已在主表且非豁免）
            if ($item->vat_number && $item->cleanse_status !== 'exempt') {
                if (Supplier::where('vat_number', $item->vat_number)->exists()) {
                    $skipped++;
                    continue;
                }
            }

            $supplier = Supplier::create([
                'name'              => $item->vendor_name,
                'code'              => $item->vendor_code,
                'vat_number'        => $item->vat_number,
                'erp_vendor_codes'  => $item->erp_vendor_codes,
                'country_code'      => $item->country_code,
                'industry'          => $item->material_group,
                'spend_amount'      => $item->spend_amount,
                'tier'              => 1,
                'status'            => 'inactive',
                'onboarding_stage'  => 'potential',
                'profile_completed' => false,
            ]);

            if ($item->primary_email) {
                SupplierContact::create([
                    'supplier_id' => $supplier->id,
                    'name'        => $item->vendor_name,
                    'email'       => $item->primary_email,
                    'is_primary'  => true,
                ]);
            }

            $item->update(['cleanse_status' => 'approved']);
            $approved++;
        }

        return ['approved_count' => $approved, 'skipped_count' => $skipped];
    }

    private function isValidEmail(?string $email): bool
    {
        if (empty($email)) return false;
        if (strtolower(trim($email)) === 'na') return false;
        return (bool) filter_var(trim($email), FILTER_VALIDATE_EMAIL);
    }
}
