<?php

namespace App\Http\Controllers\Api\Compliance;

use App\Http\Controllers\Controller;
use App\Models\SalesProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuyerProductImportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $path    = $request->file('file')->getRealPath();
        $lines   = array_filter(array_map('str_getcsv', file($path)));
        $headers = array_map('trim', array_shift($lines));

        $required = ['name'];
        foreach ($required as $col) {
            if (!in_array($col, $headers)) {
                return response()->json([
                    'success' => false,
                    'message' => "CSV 缺少必要欄位：{$col}",
                ], 422);
            }
        }

        $created  = 0;
        $skipped  = 0;
        $warnings = [];
        $lineNo   = 1;

        foreach ($lines as $row) {
            $lineNo++;
            $data = array_combine($headers, array_pad($row, count($headers), ''));

            $name = trim($data['name'] ?? '');
            if (!$name) {
                $warnings[] = "第 {$lineNo} 行：name 為必填，已跳過";
                $skipped++;
                continue;
            }

            $productCode = trim($data['product_code'] ?? '') ?: null;

            // 若 product_code 重複則跳過（以 product_code 判斷唯一性）
            if ($productCode && SalesProduct::where('product_code', $productCode)->exists()) {
                $warnings[] = "第 {$lineNo} 行（{$productCode}）：product_code 已存在，已跳過";
                $skipped++;
                continue;
            }

            SalesProduct::create([
                'name'                => $name,
                'product_code'        => $productCode,
                'description'         => trim($data['description'] ?? '') ?: null,
                'hs_code'             => trim($data['hs_code'] ?? '') ?: null,
                'unit'                => trim($data['unit'] ?? '') ?: 'pcs',
                'is_cbam_applicable'  => in_array(strtolower(trim($data['is_cbam_applicable'] ?? '')), ['1', 'true', 'yes', '是']),
                'is_eudr_applicable'  => in_array(strtolower(trim($data['is_eudr_applicable'] ?? '')), ['1', 'true', 'yes', '是']),
            ]);

            $created++;
        }

        return response()->json([
            'success'       => true,
            'created_count' => $created,
            'skipped_count' => $skipped,
            'warnings'      => $warnings,
        ]);
    }
}
