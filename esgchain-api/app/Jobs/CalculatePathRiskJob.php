<?php

namespace App\Jobs;

use App\Models\TradeGood;
use App\Models\TradeGoodPathRisk;
use App\Services\Compliance\MarketComplianceChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 計算成品在指定出口市場的合規義務缺口風險。
 *
 * 風險來自「法規義務文件缺口」，與供應商 ESG 評分無關。
 *
 * path_risk_score = obligation_score × amplifier
 * obligation_score = Σ(missing × 1.0 + expiring × 0.5) × rule.weight
 * amplifier        = 1 + (missing_count / total_required)
 */
class CalculatePathRiskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        private readonly string $tradeGoodId,
        private readonly string $market,
    ) {}

    public function handle(MarketComplianceChecker $checker): void
    {
        $product = TradeGood::with([
            'tradeGoodSuppliers.materialGroup',
            'tradeGoodSuppliers.supplier',
        ])->find($this->tradeGoodId);

        if (!$product) {
            return;
        }

        try {
            $compliance = $checker->check($product, $this->market);
            $result     = $this->calcRisk($compliance);

            TradeGoodPathRisk::updateOrCreate(
                ['trade_good_id' => $this->tradeGoodId, 'market' => $this->market],
                [
                    'path_risk_score' => $result['path_risk_score'],
                    'risk_level'      => $result['risk_level'],
                    'amplifier'       => $result['amplifier'],
                    'chain_risk'      => $result['obligation_score'],   // 放大前的原始分
                    'has_data_gap'    => $result['has_data_gap'],
                    'contributors'    => $result['obligations'],
                    'calculated_at'   => now(),
                    'expires_at'      => now()->addHours(24),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('CalculatePathRiskJob 失敗', [
                'trade_good_id' => $this->tradeGoodId,
                'market'        => $this->market,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    /**
     * 將 MarketComplianceChecker 結果轉換為路徑風險分數。
     *
     * 若無適用規則（emptyResult），回傳 very_low 0.0。
     */
    private function calcRisk(array $compliance): array
    {
        $results  = $compliance['results'] ?? [];
        $required = $compliance['required'] ?? [];

        // 無適用義務 → 無風險
        if (empty($required)) {
            return [
                'path_risk_score' => 0.0,
                'risk_level'      => 'very_low',
                'amplifier'       => 1.0,
                'obligation_score' => 0.0,
                'has_data_gap'    => false,
                'obligations'     => [],
            ];
        }

        $totalRequired = count($required);
        $missingCount  = 0;
        $obligationScore = 0.0;

        $obligations = [];
        foreach ($results as $ob) {
            $weight = $ob['is_mandatory'] ? 1.0 : 0.5;
            $status = $ob['status'];

            $contribution = match ($status) {
                'missing'       => 1.0 * $weight,
                'expiring_soon' => 0.5 * $weight,
                'expired'       => 1.0 * $weight,
                default         => 0.0,
            };

            if (in_array($status, ['missing', 'expired'])) {
                $missingCount++;
            }

            $obligationScore += $contribution;

            $obligations[] = [
                'doc_type'     => $ob['doc_type'],
                'status'       => $status,
                'is_mandatory' => $ob['is_mandatory'],
                'contribution' => round($contribution, 3),
                'expires_at'   => $ob['expires_at'] ?? null,
            ];
        }

        // 缺口放大係數：缺失比例越高，放大越多（1.0 ~ 2.0）
        $coverageGap  = $totalRequired > 0 ? $missingCount / $totalRequired : 0;
        $amplifier    = round(1 + $coverageGap, 3);
        $pathRiskScore = round($obligationScore * $amplifier, 3);

        return [
            'path_risk_score' => $pathRiskScore,
            'risk_level'      => $this->scoreToLevel($pathRiskScore),
            'amplifier'       => $amplifier,
            'obligation_score' => round($obligationScore, 3),
            'has_data_gap'    => $missingCount > 0,
            'obligations'     => $obligations,
        ];
    }

    private function scoreToLevel(float $score): string
    {
        return match (true) {
            $score >= 1.6 => 'extreme',
            $score >= 1.2 => 'high',
            $score >= 0.8 => 'medium',
            $score >= 0.4 => 'low',
            default       => 'very_low',
        };
    }
}
