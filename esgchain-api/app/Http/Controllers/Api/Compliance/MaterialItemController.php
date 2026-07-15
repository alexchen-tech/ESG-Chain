<?php

namespace App\Http\Controllers\Api\Compliance;

use App\Http\Controllers\Controller;
use App\Models\MaterialGroup;
use App\Models\MaterialItem;
use App\Models\MaterialItemEmission;
use App\Models\ProductBomLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MaterialItem::with('materialGroup')
            ->orderBy('item_code');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('item_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        if ($request->filled('material_group_ids')) {
            $ids = array_filter(explode(',', $request->input('material_group_ids')));
            if (!empty($ids)) {
                $query->whereIn('material_group_id', $ids);
            }
        }

        $perPage = (int) $request->input('per_page', 20);
        $items = $query->paginate($perPage);

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function show(MaterialItem $materialItem): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $materialItem->load('materialGroup'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => '料號代碼（item_code）僅可透過 ERP 同步或 CSV 匯入建立，一般 API 不可直接新增料號，請使用「匯入」功能',
        ], 422);
    }

    public function update(Request $request, MaterialItem $materialItem): JsonResponse
    {
        if ($request->filled('item_code')) {
            return response()->json([
                'success' => false,
                'message' => '料號代碼（item_code）僅可透過 ERP 同步或 CSV 匯入建立，不可修改',
            ], 422);
        }

        $validated = $request->validate([
            'name'              => ['sometimes', 'string', 'max:200'],
            'hs_code'           => ['nullable', 'string', 'max:20'],
            'unit'              => ['nullable', 'string', 'max:20'],
            'material_group_id' => ['nullable', 'uuid', 'exists:material_groups,id'],
            'description'       => ['nullable', 'string'],
            'is_active'         => ['boolean'],
            'net_weight'           => ['nullable', 'numeric', 'min:0'],
            'pcr_percentage'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pir_percentage'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bio_based_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'recyclability_rating' => ['nullable', 'in:high,medium,low,not_rated'],
        ]);

        $materialItem->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $materialItem->fresh()->load('materialGroup'),
        ]);
    }

    public function destroy(MaterialItem $materialItem): JsonResponse
    {
        $bomLineCount = $materialItem->bomLines()->count();
        if ($bomLineCount > 0) {
            return response()->json([
                'success'       => false,
                'message'       => "此料號被 {$bomLineCount} 條 BOM 明細使用中，無法刪除。可選擇「停用」以保留記錄。",
                'bom_line_count' => $bomLineCount,
            ], 422);
        }

        $materialItem->delete();

        return response()->json(['success' => true, 'message' => '料號已刪除']);
    }

    public function bomSuppliers(MaterialItem $materialItem): JsonResponse
    {
        // 從 BOM 明細推算：此物料被哪些 primary supplier 所供應
        $bomLines = ProductBomLine::where('material_item_id', $materialItem->id)
            ->with(['bomLineSuppliers' => fn ($q) => $q->where('role', 'primary')->with('supplier')])
            ->get();

        // 彙整 supplier_id -> { supplier_name, bom_count }
        $supplierMap = [];
        foreach ($bomLines as $line) {
            foreach ($line->bomLineSuppliers as $bls) {
                $sid = $bls->supplier_id;
                if (!isset($supplierMap[$sid])) {
                    $supplierMap[$sid] = [
                        'supplier_id'   => $sid,
                        'supplier_name' => $bls->supplier->name ?? $sid,
                        'bom_count'     => 0,
                        'latest_emission' => null,
                    ];
                }
                $supplierMap[$sid]['bom_count']++;
            }
        }

        // 補上最新碳排記錄
        foreach ($supplierMap as $sid => &$entry) {
            $emission = MaterialItemEmission::where('material_item_id', $materialItem->id)
                ->where('supplier_id', $sid)
                ->latest('reported_at')
                ->first();

            if ($emission) {
                $entry['latest_emission'] = [
                    'emissions_value' => $emission->emissions_value,
                    'source'          => $emission->source,
                    'is_flagged'      => (bool) $emission->is_flagged,
                    'reported_period' => $emission->reported_period,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => array_values($supplierMap),
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt']]);

        $file     = $request->file('file');
        $handle   = fopen($file->getRealPath(), 'r');
        $headers  = fgetcsv($handle);
        $headers  = array_map('trim', $headers);

        $created  = 0;
        $updated  = 0;
        $warnings = [];
        $row      = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (count($data) < 2) continue;

            $record = array_combine($headers, array_map('trim', $data));

            $itemCode = $record['item_code'] ?? null;
            $name     = $record['name'] ?? null;

            if (empty($itemCode) || empty($name)) {
                $warnings[] = "第 {$row} 行：item_code 和 name 為必填，已跳過";
                continue;
            }

            $mgId = null;
            if (!empty($record['material_group_name'])) {
                $mg = MaterialGroup::where('name', $record['material_group_name'])->first();
                if ($mg) {
                    $mgId = $mg->id;
                } else {
                    $warnings[] = "第 {$row} 行（{$itemCode}）：物料群組「{$record['material_group_name']}」找不到對應，material_group_id 留空";
                }
            }

            $exists = MaterialItem::where('item_code', $itemCode)->first();
            if ($exists) {
                $exists->update([
                    'name'              => $name,
                    'hs_code'           => $record['hs_code'] ?? null,
                    'unit'              => $record['unit'] ?? null,
                    'material_group_id' => $mgId ?? $exists->material_group_id,
                ]);
                $updated++;
            } else {
                MaterialItem::create([
                    'item_code'         => $itemCode,
                    'name'              => $name,
                    'hs_code'           => $record['hs_code'] ?? null,
                    'unit'              => $record['unit'] ?? null,
                    'material_group_id' => $mgId,
                    'is_active'         => true,
                ]);
                $created++;
            }
        }

        fclose($handle);

        return response()->json([
            'success'  => true,
            'data'     => ['created' => $created, 'updated' => $updated, 'warnings' => $warnings],
            'message'  => "匯入完成：新增 {$created} 筆，更新 {$updated} 筆" . (count($warnings) ? '，有 ' . count($warnings) . ' 筆警告' : ''),
        ]);
    }
}
