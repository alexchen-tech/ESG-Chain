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
            'fiber_type'           => ['nullable', 'string', 'max:50'],
            'microfiber_release_risk' => ['nullable', 'in:low,medium,high,not_rated'],
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

    /**
     * 此物料在整個產品組合中，各產品的 BOM 行分別指定了哪個供應商為 primary，
     * 以及該 BOM 行核准供應商名單（AVL）中每家的碳排比較資料，供「切換主供應商」操作使用。
     * 一個物料可能被多個產品的 BOM 各自指到不同供應商——這裡逐行（逐產品）攤開，
     * 而非依供應商彙整，讓使用者看得出「同一物料在產品組合裡的供應商分布」。
     */
    public function bomSuppliers(MaterialItem $materialItem): JsonResponse
    {
        $bomLines = ProductBomLine::where('material_item_id', $materialItem->id)
            ->with(['salesProduct:id,name,product_code,model_no', 'bomLineSuppliers.supplier:id,name,code'])
            ->get();

        $emissionsBySupplier = MaterialItemEmission::where('material_item_id', $materialItem->id)
            ->whereNotNull('supplier_id')
            ->orderByDesc('reported_at')
            ->get()
            ->unique('supplier_id')
            ->keyBy('supplier_id');

        $rows = $bomLines->map(function ($line) use ($emissionsBySupplier) {
            $suppliers = $line->bomLineSuppliers->map(function ($bls) use ($emissionsBySupplier) {
                $emission = $emissionsBySupplier->get($bls->supplier_id);
                return [
                    'bom_line_supplier_id' => $bls->id,
                    'supplier_id'          => $bls->supplier_id,
                    'supplier_name'        => $bls->supplier?->name ?? $bls->supplier_id,
                    'supplier_code'        => $bls->supplier?->code,
                    'role'                 => $bls->role,
                    'emissions_value'      => $emission?->emissions_value,
                    'source'               => $emission?->source,
                    'is_flagged'           => (bool) ($emission?->is_flagged ?? false),
                    'reported_period'      => $emission?->reported_period,
                ];
            })->sortBy(fn ($s) => $s['emissions_value'] ?? PHP_FLOAT_MAX)->values();

            $primary = $suppliers->firstWhere('role', 'primary');

            return [
                'bom_line_id'                 => $line->id,
                'sales_product_id'            => $line->sales_product_id,
                'product_name'                => $line->salesProduct?->name,
                'product_code'                => $line->salesProduct?->product_code,
                'product_model_no'            => $line->salesProduct?->model_no,
                'current_primary_supplier_id' => $primary['supplier_id'] ?? null,
                'suppliers'                   => $suppliers,
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * 切換某 BOM 行的主供應商——把選定的 AVL 供應商設為 primary，原 primary 降為 alternate。
     * 目標供應商必須已在此 BOM 行的核准供應商名單（AVL）中。
     */
    public function switchPrimarySupplier(Request $request, MaterialItem $materialItem, ProductBomLine $bomLine): JsonResponse
    {
        abort_if($bomLine->material_item_id !== $materialItem->id, 404);

        $validated = $request->validate([
            'supplier_id' => ['required', 'uuid'],
        ]);

        $target = $bomLine->bomLineSuppliers()->where('supplier_id', $validated['supplier_id'])->first();
        if (!$target) {
            return response()->json([
                'success' => false,
                'message' => '此供應商不在該 BOM 行的核准供應商名單中，請先於 BOM 明細新增',
            ], 422);
        }

        if ($target->role === 'primary') {
            return response()->json(['success' => true, 'message' => '此供應商已是主供應商', 'data' => $target]);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($bomLine, $target) {
            $bomLine->bomLineSuppliers()
                ->where('role', 'primary')
                ->where('id', '!=', $target->id)
                ->update(['role' => 'alternate']);
            $target->update(['role' => 'primary']);
        });

        \App\Jobs\PcfEmissionGapScanJob::dispatch($bomLine->sales_product_id, $target->supplier_id, 'system_supplier_change');

        $salesProduct = \App\Models\SalesProduct::find($bomLine->sales_product_id);
        if ($salesProduct) {
            dispatch(function () use ($salesProduct) {
                app(\App\Services\PCF\PcfCalculationService::class)->snapshot($salesProduct);
            })->afterResponse();
        }

        return response()->json([
            'success' => true,
            'data'    => $target->fresh()->load('supplier'),
            'message' => '主供應商已切換',
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

            $recyclability = $record['recyclability_rating'] ?? null;
            if ($recyclability !== null && $recyclability !== '' && !in_array($recyclability, ['high', 'medium', 'low', 'not_rated'], true)) {
                $warnings[] = "第 {$row} 行（{$itemCode}）：recyclability_rating「{$recyclability}」不在 high/medium/low/not_rated 範圍內，已忽略";
                $recyclability = null;
            }

            $extra = array_filter([
                'description'          => $record['description'] ?? null,
                'net_weight'           => $record['net_weight'] ?? null,
                'pcr_percentage'       => $record['pcr_percentage'] ?? null,
                'pir_percentage'       => $record['pir_percentage'] ?? null,
                'bio_based_percentage' => $record['bio_based_percentage'] ?? null,
                'recyclability_rating' => $recyclability,
                'fiber_type'           => $record['fiber_type'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            $exists = MaterialItem::where('item_code', $itemCode)->first();
            if ($exists) {
                $exists->update(array_merge([
                    'name'              => $name,
                    'hs_code'           => $record['hs_code'] ?? null,
                    'unit'              => $record['unit'] ?? null,
                    'material_group_id' => $mgId ?? $exists->material_group_id,
                ], $extra));
                $updated++;
            } else {
                MaterialItem::create(array_merge([
                    'item_code'         => $itemCode,
                    'name'              => $name,
                    'hs_code'           => $record['hs_code'] ?? null,
                    'unit'              => $record['unit'] ?? null,
                    'material_group_id' => $mgId,
                    'is_active'         => true,
                ], $extra));
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
