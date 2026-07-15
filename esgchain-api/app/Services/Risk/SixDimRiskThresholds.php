<?php

namespace App\Services\Risk;

/**
 * 六維度各別風險閾值（合規分下限，低於此值 = 高風險）。
 *
 * dim_eN 是合規分（0–100，高=好）；低於閾值代表該維度合規性不足。
 * E6 僅在供應商有適用法規（regulations 非空）時才納入判斷。
 */
class SixDimRiskThresholds
{
    /** 各維度高風險閾值（合規分下限） */
    public const THRESHOLDS = [
        'dim_e1' => 40.0,  // 環境管理
        'dim_e2' => 45.0,  // 氣候與碳排（揭露要求較高）
        'dim_e3' => 40.0,  // 社會責任
        'dim_e4' => 35.0,  // 地緣風險（客觀指標影響大，寬容度較低）
        'dim_e5' => 40.0,  // 公司治理
        'dim_e6' => 50.0,  // 法規準備（僅在 regulations 非空時啟用）
    ];

    /** 供應商替代推薦的最差維度底線（任一 E1-E5 低於此值即排除候選廠） */
    public const REPLACEMENT_MIN_SCORE = 30.0;

    /**
     * 判斷哪些維度觸發高風險。
     *
     * @param  array<string, float|null>  $dims  鍵為 dim_e1–dim_e6，值為合規分（null = 無資料）
     * @param  string[]                   $regulations  供應商適用法規列表
     * @return string[]  觸發閾值的維度清單（e.g. ['dim_e2', 'dim_e5']）
     */
    public static function getRiskDims(array $dims, array $regulations = []): array
    {
        $riskDims = [];

        foreach (self::THRESHOLDS as $field => $threshold) {
            $score = $dims[$field] ?? null;

            if ($score === null) {
                // E6 null 且有法規 → 資料缺口，列入風險
                if ($field === 'dim_e6' && count($regulations) > 0) {
                    $riskDims[] = $field;
                }
                // 其他維度 null → 不計入（資料缺口由 MarketComplianceChecker 處理）
                continue;
            }

            // E6 無適用法規時跳過
            if ($field === 'dim_e6' && count($regulations) === 0) {
                continue;
            }

            if ((float) $score < $threshold) {
                $riskDims[] = $field;
            }
        }

        return $riskDims;
    }

    /**
     * 是否為高風險供應商（任一維度觸發閾值）。
     *
     * @param  array<string, float|null>  $dims
     * @param  string[]                   $regulations
     */
    public static function isHighRisk(array $dims, array $regulations = []): bool
    {
        return count(self::getRiskDims($dims, $regulations)) > 0;
    }
}
