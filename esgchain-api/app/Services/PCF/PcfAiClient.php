<?php

namespace App\Services\PCF;

use Illuminate\Support\Facades\Http;

/**
 * 呼叫 esgchain-ai 產品 PCF 計算端點（server-to-server，X-Internal-Token）。
 * esgchain-api 只蒐集 BOM 行原始資料，實際 total_pcf / iso14067_ready / pcr_ratio
 * 計算在 esgchain-ai（CLAUDE.md：計算邏輯不可寫在 esgchain-api）。
 */
class PcfAiClient
{
    public function calculate(string $productId, string $unit, array $lines): array
    {
        $aiUrl = rtrim(config('services.ai.url'), '/');

        $response = Http::withHeaders(['X-Internal-Token' => config('services.ai.internal_token')])
            ->timeout(config('services.ai.timeout', 120))
            ->post("{$aiUrl}/ai/v1/product-pcf/calculate", [
                'sales_product_id' => $productId,
                'functional_unit'  => $unit,
                'lines'            => $lines,
            ]);

        $response->throw();

        return $response->json();
    }
}
