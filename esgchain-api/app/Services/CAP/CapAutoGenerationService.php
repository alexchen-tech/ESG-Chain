<?php

namespace App\Services\CAP;

use App\Models\CAP;
use App\Models\RiskAssessment;
use App\Models\SAQ;
use App\Services\Risk\SixDimRiskThresholds;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 依 RiskAssessment 六維合規分自動建立 CAP。
 *
 * 觸發規則（合規分，低=壞）：
 *   dim_eN < 閾值（extreme zone, < threshold × 0.5）→ 自動建立 CAP
 *   dim_eN 介於 [threshold×0.5, threshold) → 記錄 Log 通知
 */
class CapAutoGenerationService
{
    /** 六維矯正方向提示模板 */
    private const DIM_TEMPLATES = [
        'dim_e1' => [
            'label'           => '環境管理',
            'suggested_actions' => '建議取得 ISO 14001 環境管理系統認證，或強化現有 EMS 的文件化與監測機制，定期報告環境績效指標（廢水、廢棄物、能源強度）。',
        ],
        'dim_e2' => [
            'label'           => '氣候與碳排',
            'suggested_actions' => '建議完成 Scope 1/2 碳排放盤查並依 GHG Protocol 揭露，設定 2030 年碳減量目標，並規劃科學基礎減量路徑（SBTi）。',
        ],
        'dim_e3' => [
            'label'           => '社會責任',
            'suggested_actions' => '建議依 SA8000 或 RBA 行為準則檢視勞工條件（工時、薪資、禁止強迫勞動），完善申訴機制並定期進行社會責任稽核。',
        ],
        'dim_e4' => [
            'label'           => '地緣風險',
            'suggested_actions' => '建議建立業務連續性計畫（BCP）應對地緣政治中斷，評估關鍵原物料替代來源國，並定期演練緊急供應鏈轉換程序。',
        ],
        'dim_e5' => [
            'label'           => '公司治理',
            'suggested_actions' => '建議強化董事會獨立性與反腐敗政策，完善資訊揭露（財務、ESG 報告），並建立內部舉報管道與合規訓練計畫。',
        ],
        'dim_e6' => [
            'label'           => '法規準備',
            'suggested_actions' => '建議評估 CBAM、EUDR、UFLPA 等適用法規的合規缺口，指定法規合規負責人，並建立法規變動監控與文件管理機制。',
        ],
    ];

    public function generateFromRisk(RiskAssessment $ra, SAQ $saq, array $categoryScores = []): void
    {
        $dims = [
            'dim_e1' => $ra->dim_e1,
            'dim_e2' => $ra->dim_e2,
            'dim_e3' => $ra->dim_e3,
            'dim_e4' => $ra->dim_e4,
            'dim_e5' => $ra->dim_e5,
            'dim_e6' => $ra->dim_e6,
        ];

        foreach (SixDimRiskThresholds::THRESHOLDS as $dimField => $threshold) {
            $score = $dims[$dimField] ?? null;
            if ($score === null) continue;

            $extremeBoundary = $threshold * 0.5;

            if ((float) $score < $extremeBoundary) {
                // extreme zone → 自動建立 CAP
                $this->buildDimCap($ra, $saq, $dimField, (float) $score, $threshold);
            } elseif ((float) $score < $threshold) {
                // high zone → Log 通知
                $this->notifyHighRisk($saq, $dimField, (float) $score);
            }
        }
    }

    private function buildDimCap(
        RiskAssessment $ra,
        SAQ $saq,
        string $dimField,
        float $score,
        float $threshold
    ): void {
        if ($this->capAlreadyExists($saq->id, $dimField)) {
            return;
        }

        $template = self::DIM_TEMPLATES[$dimField];
        $label    = $template['label'];

        $cap = CAP::create([
            'supplier_id'       => $saq->supplier_id,
            'saq_id'            => $saq->id,
            'source_type'       => 'risk_assessment',
            'source_id'         => $ra->id,
            'triggered_by_axis' => $dimField,
            'auto_generated'    => true,
            'title'             => sprintf('[%s] %s 合規分嚴重不足（%s / 100，閾值 %s）', strtoupper($dimField), $label, round($score, 1), $threshold),
            'priority'          => 'critical',
            'status'            => 'open',
            'due_date'          => now()->addDays(30)->toDateString(),
            'created_by'        => null,
        ]);

        $cap->findings()->create([
            'framework'    => $dimField,
            'topic_slug'   => null,
            'source_score' => round($score, 2),
            'threshold'    => $threshold,
            'finding'      => sprintf('%s 合規分 %s/100，低於閾值 %s，需立即進行改善', $label, round($score, 1), $threshold),
            'status'       => 'open',
        ]);

        // 在 CAP description / suggested_actions 欄位寫入模板文字（若有欄位）
        if ($cap->getConnection()->getSchemaBuilder()->hasColumn('cap_actions', 'suggested_actions')) {
            $cap->update(['suggested_actions' => $template['suggested_actions']]);
        }
    }

    /**
     * 冪等保護：該 SAQ + dim 已有 open auto-generated CAP 時跳過。
     * 相容舊值（axis1/axis2/axis3）與新值（dim_e1–dim_e6）。
     */
    private function capAlreadyExists(string $saqId, string $dimField): bool
    {
        return CAP::where('saq_id', $saqId)
            ->where('triggered_by_axis', $dimField)
            ->where('auto_generated', true)
            ->whereNotIn('status', ['closed'])
            ->exists();
    }

    private function notifyHighRisk(SAQ $saq, string $dimField, float $score): void
    {
        $label = self::DIM_TEMPLATES[$dimField]['label'] ?? $dimField;
        Log::info('CAP auto-generation: high risk detected (no CAP created)', [
            'supplier_id' => $saq->supplier_id,
            'saq_id'      => $saq->id,
            'dim'         => $dimField,
            'label'       => $label,
            'score'       => $score,
        ]);
    }
}
