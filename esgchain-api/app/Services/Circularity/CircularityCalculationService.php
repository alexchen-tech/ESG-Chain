<?php

namespace App\Services\Circularity;

use App\Models\ProductCircularitySnapshot;
use App\Models\SalesProduct;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CircularityCalculationService
{
    /**
     * 蒐集 BOM 品項＋物料循環經濟欄位、呼叫 esgchain-ai 加權彙總、存成 append-only 快照。
     * 本服務只負責蒐集與存檔，實際加權計算在 esgchain-ai（App\Services 不可做計算，見 CLAUDE.md）。
     */
    public function snapshot(SalesProduct $product): ProductCircularitySnapshot
    {
        $lines = $this->buildLines($product);
        $result = $this->callAi($product->id, $lines);

        return ProductCircularitySnapshot::create([
            'sales_product_id'        => $product->id,
            'total_weight_kg'         => $result['total_weight_kg'] ?? null,
            'recycled_content_ratio'  => $result['recycled_content_ratio'] ?? null,
            'pcr_ratio'               => $result['pcr_ratio'] ?? null,
            'pir_ratio'               => $result['pir_ratio'] ?? null,
            'bio_based_ratio'         => $result['bio_based_ratio'] ?? null,
            'composition_breakdown'   => $result['composition_breakdown'] ?? [],
            'recyclability_summary'   => $result['recyclability_summary'] ?? [],
            'incomplete_lines_count'  => $result['incomplete_lines_count'] ?? $lines->count(),
            'data_ready'              => $result['data_ready'] ?? false,
            'calculated_at'           => now(),
        ]);
    }

    private function buildLines(SalesProduct $product)
    {
        return $product->bomLines()
            ->with('materialItem')
            ->get()
            ->map(function ($bomLine) {
                $item = $bomLine->materialItem;
                return [
                    'material_item_id'      => $item?->id,
                    'fiber_type'            => $item?->fiber_type,
                    'net_weight'            => $item?->net_weight,
                    'quantity'              => (float) ($bomLine->quantity ?? 1),
                    'pcr_percentage'        => $item?->pcr_percentage,
                    'pir_percentage'        => $item?->pir_percentage,
                    'bio_based_percentage'  => $item?->bio_based_percentage,
                    'recyclability_rating'  => $item?->recyclability_rating,
                ];
            });
    }

    /**
     * 呼叫 esgchain-ai composition 計算端點。AI 不可用時回傳空陣列，快照仍會建立但標記資料不足。
     */
    private function callAi(string $salesProductId, $lines): array
    {
        try {
            $aiUrl = rtrim(config('services.ai.url', env('AI_SERVICE_URL', 'http://esgchain-ai:8000')), '/');
            $resp = Http::timeout(10)->post("{$aiUrl}/ai/v1/composition/calculate", [
                'sales_product_id' => $salesProductId,
                'lines'            => $lines->values()->all(),
            ]);

            if ($resp->successful()) {
                return $resp->json();
            }
            Log::warning('composition/calculate AI 回應非成功', ['status' => $resp->status()]);
        } catch (\Throwable $e) {
            Log::warning('composition/calculate AI 呼叫失敗', ['error' => $e->getMessage()]);
        }

        return [];
    }
}
