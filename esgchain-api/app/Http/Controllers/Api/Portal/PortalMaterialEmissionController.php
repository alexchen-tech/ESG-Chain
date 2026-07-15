<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\MaterialItem;
use App\Models\MaterialItemEmission;
use App\Models\ProductBomLine;
use App\Services\PCF\MaterialEmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalMaterialEmissionController extends Controller
{
    public function __construct(
        private readonly MaterialEmissionService $service
    ) {}

    /**
     * 供應商需提報的物料清單
     * 包含：被 BOM 指定為 primary 的物料 + 已主動提報的物料
     */
    public function index(): JsonResponse
    {
        $supplierId = auth()->user()->supplier_id;

        // BOM 中指定此供應商為 primary 的物料
        $bomMaterialIds = ProductBomLine::whereHas('bomLineSuppliers', function ($q) use ($supplierId) {
            $q->where('supplier_id', $supplierId)->where('role', 'primary');
        })->with('materialItem')->get()
            ->pluck('materialItem')
            ->filter()
            ->unique('id');

        // 已提報的物料（含主動提報）
        $reportedEmissions = MaterialItemEmission::where('supplier_id', $supplierId)
            ->with('materialItem')
            ->orderByDesc('reported_at')
            ->get()
            ->groupBy('material_item_id');

        // 合併：BOM 需提報 + 已主動提報但不在 BOM 中的
        $allMaterialIds = $bomMaterialIds->pluck('id')
            ->merge($reportedEmissions->keys())
            ->unique();

        $result = $allMaterialIds->map(function ($materialItemId) use ($bomMaterialIds, $reportedEmissions, $supplierId) {
            $materialItem = $bomMaterialIds->firstWhere('id', $materialItemId)
                ?? MaterialItem::find($materialItemId);

            $emissions    = $reportedEmissions->get($materialItemId, collect());
            $latest       = $emissions->first();
            $inBom        = $bomMaterialIds->contains('id', $materialItemId);

            return [
                'material_item_id'  => $materialItemId,
                'item_code'         => $materialItem?->item_code,
                'name'              => $materialItem?->name,
                'hs_code'           => $materialItem?->hs_code,
                'unit'              => $materialItem?->unit,
                'in_bom'            => $inBom,
                'status'            => $this->resolveStatus($latest),
                'latest_emission'   => $latest ? [
                    'id'               => $latest->id,
                    'emissions_value'  => $latest->emissions_value,
                    'source'           => $latest->source,
                    'is_estimated'     => $latest->is_estimated,
                    'reported_period'  => $latest->reported_period,
                    'reported_at'      => $latest->reported_at,
                ] : null,
            ];
        })->values();

        return response()->json(['data' => $result]);
    }

    /**
     * 依最新碳排記錄的來源決定三態狀態：
     *   reported     — 供應商或買方已提報真實數值
     *   ai_estimated — 目前只有系統估算值，尚未人工提報
     *   pending      — 完全無記錄
     */
    private function resolveStatus(?MaterialItemEmission $latest): string
    {
        if (!$latest) return 'pending';
        if (in_array($latest->source, ['portal-self', 'buyer-input'], true)) return 'reported';
        return 'ai_estimated';
    }

    /**
     * 供應商提報物料碳排（source=portal-self）
     */
    public function store(Request $request): JsonResponse
    {
        $supplierId = auth()->user()->supplier_id;

        $validated = $request->validate([
            'material_item_id'   => 'required|uuid|exists:material_items,id',
            'emissions_value'    => 'required|numeric|min:0',
            'reported_period'    => 'nullable|string|max:20',
            'calculation_method' => 'nullable|string|max:100',
            'calculation_note'   => 'nullable|string|max:1000',
        ]);

        $emission = $this->service->record(
            $validated['material_item_id'],
            $supplierId,
            $validated,
            'portal-self'
        );

        return response()->json(['data' => $emission], 201);
    }

    /**
     * 搜尋物料主檔（供主動提報用）
     */
    public function searchMaterials(Request $request): JsonResponse
    {
        $keyword = $request->query('keyword', '');

        $materials = MaterialItem::where('is_active', true)
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('item_code', 'like', "%{$keyword}%")
                  ->orWhere('hs_code', 'like', "%{$keyword}%");
            })
            ->select('id', 'item_code', 'name', 'hs_code', 'unit')
            ->limit(20)
            ->get();

        return response()->json(['data' => $materials]);
    }
}
