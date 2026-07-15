<?php

namespace App\Services\PCF;

use App\Models\MaterialItem;
use App\Models\MaterialItemEmission;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaterialEmissionService
{
    // 自動標記的異常閾值（kgCO₂e/unit）
    private const FLAG_THRESHOLD_HIGH = 10000.0;
    private const FLAG_THRESHOLD_LOW  = 0.000001;

    // 來源優先級：數字越小優先級越高
    private const SOURCE_PRIORITY = [
        'portal-self'    => 1,
        'buyer-input'    => 2,
        'ai-estimated'   => 3,
        'system_default' => 4,
    ];

    /**
     * 依供應商分組列出某物料的所有碳排記錄，含最新值 + 歷史筆數
     */
    public function listForMaterial(string $materialItemId): Collection
    {
        $emissions = MaterialItemEmission::where('material_item_id', $materialItemId)
            ->with('supplier')
            ->orderBy('supplier_id')
            ->orderByDesc('reported_at')
            ->get();

        return $emissions->groupBy('supplier_id')->map(function ($group, $supplierId) {
            $latest = $group->first();
            return [
                'supplier_id'    => $supplierId,
                'supplier_name'  => $latest->supplier?->name ?? '（買方代填）',
                'latest'         => $latest,
                'history_count'  => $group->count(),
                'history'        => $group->slice(1)->values(),
            ];
        })->values();
    }

    /**
     * 新增一筆碳排記錄，超出合理範圍自動 flag
     */
    public function record(
        string $materialItemId,
        ?string $supplierId,
        array $payload,
        string $source
    ): MaterialItemEmission {
        $emissionsValue = (float) $payload['emissions_value'];
        $isFlagged      = false;
        $flagReason     = null;

        if ($emissionsValue > self::FLAG_THRESHOLD_HIGH) {
            $isFlagged  = true;
            $flagReason = "數值過高（{$emissionsValue} kgCO₂e），超過系統閾值 " . self::FLAG_THRESHOLD_HIGH;
        } elseif ($emissionsValue < self::FLAG_THRESHOLD_LOW && $emissionsValue > 0) {
            $isFlagged  = true;
            $flagReason = "數值過低（{$emissionsValue} kgCO₂e），低於系統閾值 " . self::FLAG_THRESHOLD_LOW;
        }

        return MaterialItemEmission::create([
            'material_item_id'   => $materialItemId,
            'supplier_id'        => $supplierId,
            'emissions_value'    => $emissionsValue,
            'source'             => $source,
            'calculation_method' => $payload['calculation_method'] ?? null,
            'reported_period'    => $payload['reported_period'] ?? null,
            'is_estimated'       => $source === 'ai-estimated',
            'is_flagged'         => $isFlagged,
            'flag_reason'        => $flagReason,
        ]);
    }

    /**
     * 標記異常
     */
    public function flag(string $emissionId, string $reason): MaterialItemEmission
    {
        $emission = MaterialItemEmission::findOrFail($emissionId);
        $emission->update([
            'is_flagged'  => true,
            'flag_reason' => $reason,
        ]);
        return $emission->fresh();
    }

    /**
     * 取消標記
     */
    public function unflag(string $emissionId): MaterialItemEmission
    {
        $emission = MaterialItemEmission::findOrFail($emissionId);
        $emission->update([
            'is_flagged'  => false,
            'flag_reason' => null,
        ]);
        return $emission->fresh();
    }

    /**
     * 依優先級取最佳碳排值（portal-self > buyer-input > ai-estimated > system_default），排除已標記異常。
     * 若該供應商無任何記錄，自動 fallback 至 supplier_id=null 的 system_default。
     */
    public function getBestEmissionForSupplier(
        string $materialItemId,
        string $supplierId
    ): ?MaterialItemEmission {
        $candidates = MaterialItemEmission::where('material_item_id', $materialItemId)
            ->where('supplier_id', $supplierId)
            ->where('is_flagged', false)
            ->get();

        if ($candidates->isNotEmpty()) {
            return $candidates->sortBy(function ($emission) {
                $priorityScore = self::SOURCE_PRIORITY[$emission->source] ?? 99;
                return [$priorityScore, -$emission->reported_at->timestamp];
            })->first();
        }

        // Fallback：使用 system_default（supplier_id=null）
        return MaterialItemEmission::where('material_item_id', $materialItemId)
            ->whereNull('supplier_id')
            ->where('source', 'system_default')
            ->where('is_flagged', false)
            ->orderByDesc('reported_at')
            ->first();
    }
}
