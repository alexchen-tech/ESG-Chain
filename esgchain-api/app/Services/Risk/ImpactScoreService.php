<?php

namespace App\Services\Risk;

use App\Models\BomLineSupplier;
use App\Models\ProductBomLine;
use App\Models\SalesProduct;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 供應商風險矩陣 Impact 值（1–5）計算協調器。
 *
 * 遵守 CLAUDE.md：計分邏輯集中在 esgchain-ai。本 Service 僅負責：
 *   1. 蒐集四因子原始輸入（tier / spend / 單一來源 / 材料關鍵性）
 *   2. 呼叫 esgchain-ai /ai/v1/impact-scoring 取得分數
 *   3. 以 query builder 寫回 suppliers.impact_score（不觸發 Model 事件，避免遞迴）
 */
class ImpactScoreService
{
    /**
     * 重算並寫回單一供應商的 impact_score。
     *
     * @return int|null 計算成功回傳 1–5；AI 不可用或供應商不存在回傳 null（不覆寫既有值）
     */
    public function recomputeForSupplier(string $supplierId): ?int
    {
        $supplier = Supplier::find($supplierId);
        if (!$supplier) {
            return null;
        }

        $payload = [
            'tier'             => $supplier->tier,
            'spend_amount'     => $supplier->spend_amount,
            'spend_thresholds' => $this->spendThresholds(),
            'single_source'    => $this->deriveSingleSource($supplierId),
            'regulations'      => $this->deriveRegulations($supplierId),
        ];

        $score = $this->callAi($payload);
        if ($score === null) {
            return null; // AI 不可用時保留既有 impact_score
        }

        // query builder 更新，繞過 Model 事件（避免 SupplierObserver 遞迴）
        Supplier::where('id', $supplierId)->update(['impact_score' => $score]);

        return $score;
    }

    /**
     * spend 固定門檻（存 system_settings，可調）。
     */
    private function spendThresholds(): array
    {
        $setting = DB::table('system_settings')->where('key', 'impact_spend_thresholds')->first();
        if ($setting) {
            return json_decode($setting->value, true) ?? [];
        }
        return ['s5' => 10000000, 's4' => 3000000, 's3' => 1000000, 's2' => 300000];
    }

    /**
     * 單一來源依賴：供應商供應的任一 BOM line 若僅由它一家供應 → true；
     * 全部多來源 → false；供應商無任何 BOM 供應關係 → null（無資料）。
     */
    private function deriveSingleSource(string $supplierId): ?bool
    {
        $bomLineIds = BomLineSupplier::where('supplier_id', $supplierId)
            ->pluck('bom_line_id')
            ->unique();

        if ($bomLineIds->isEmpty()) {
            return null;
        }

        // 每條相關 BOM line 的供應商數
        $counts = BomLineSupplier::whereIn('bom_line_id', $bomLineIds)
            ->selectRaw('bom_line_id, COUNT(DISTINCT supplier_id) as cnt')
            ->groupBy('bom_line_id')
            ->pluck('cnt', 'bom_line_id');

        return $counts->contains(fn($cnt) => (int) $cnt <= 1);
    }

    /**
     * 材料關鍵性：彙整供應商涉及的 SalesProduct 之 applicable/inferred 法規代碼。
     * 供應商有涉及產品 → 回傳法規清單（可能為空陣列）；完全無涉及產品 → null。
     */
    private function deriveRegulations(string $supplierId): ?array
    {
        $bomLineIds = BomLineSupplier::where('supplier_id', $supplierId)
            ->pluck('bom_line_id')
            ->unique();

        if ($bomLineIds->isEmpty()) {
            return null;
        }

        $salesProductIds = ProductBomLine::whereIn('id', $bomLineIds)
            ->pluck('sales_product_id')
            ->filter()
            ->unique();

        if ($salesProductIds->isEmpty()) {
            return null;
        }

        $products = SalesProduct::whereIn('id', $salesProductIds)
            ->get(['applicable_regulations', 'inferred_regulations']);

        return $products
            ->flatMap(fn($p) => array_merge(
                $p->applicable_regulations ?? [],
                $p->inferred_regulations ?? [],
            ))
            ->map(fn($r) => strtoupper((string) $r))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 呼叫 esgchain-ai 計分端點。AI 不可用時靜默回傳 null。
     */
    private function callAi(array $payload): ?int
    {
        try {
            $aiUrl = rtrim(config('services.ai.url', env('AI_SERVICE_URL', 'http://esgchain-ai:8000')), '/');
            $resp  = Http::timeout(10)->post("{$aiUrl}/ai/v1/impact-scoring", $payload);

            if ($resp->successful()) {
                $score = $resp->json('impact_score');
                return $score === null ? null : (int) $score;
            }
            Log::warning('impact-scoring AI 回應非成功', ['status' => $resp->status()]);
        } catch (\Throwable $e) {
            Log::warning('impact-scoring AI 呼叫失敗', ['error' => $e->getMessage()]);
        }

        return null;
    }
}
