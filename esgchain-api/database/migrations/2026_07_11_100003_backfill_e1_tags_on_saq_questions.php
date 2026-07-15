<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 為現有題庫題目補上 E1 tag（六維架構最低要求）。
 * pillar 依據 compliance_domains 啟發式映射：
 *   環境相關 → E；勞工/社會相關 → S；治理/採購 → G；無法辨識 → E（保守預設）
 * weight 按照每道題目原有 weight 欄位，若為 null 則預設 0.02。
 */
return new class extends Migration
{
    // compliance_domain 關鍵字 → ESG 支柱
    private const DOMAIN_TO_PILLAR = [
        'ISO14001' => 'E', 'ISO50001' => 'E', 'EUDR' => 'E', 'CBAM' => 'E',
        'ISO45001' => 'S', 'SA8000' => 'S', 'UFLPA' => 'S', 'RBA' => 'S',
        'ISO26000' => 'S',
        'ISO20400' => 'G', 'ISO37001' => 'G', 'ISO9001' => 'G', 'GRI' => 'G',
        'ISO28000' => 'G',
    ];

    public function up(): void
    {
        $questions = DB::table('saq_questions')
            ->whereNull('template_id')
            ->get(['id', 'tags', 'compliance_domains', 'weight']);

        foreach ($questions as $q) {
            $existingTags = json_decode($q->tags ?? '[]', true) ?: [];

            // 若已有 object 格式的 E1 tag，跳過
            if (!empty($existingTags) && isset($existingTags[0]['framework'])) {
                continue;
            }

            $domains     = json_decode($q->compliance_domains ?? '[]', true) ?: [];
            $pillar      = $this->inferPillar($domains);
            $weight      = $q->weight > 0 ? (float) $q->weight : 0.02;

            $newTag = ['framework' => 'E1', 'pillar' => $pillar, 'weight' => $weight];

            DB::table('saq_questions')->where('id', $q->id)->update([
                'tags'               => json_encode([$newTag]),
                'compliance_domains' => json_encode(array_unique(array_merge(['E1'], $domains))),
            ]);
        }
    }

    public function down(): void
    {
        // 無法安全還原（原始 tags 資料已覆蓋），此 down 為空操作
    }

    private function inferPillar(array $domains): string
    {
        $votes = ['E' => 0, 'S' => 0, 'G' => 0];
        foreach ($domains as $domain) {
            $pillar = self::DOMAIN_TO_PILLAR[$domain] ?? null;
            if ($pillar) {
                $votes[$pillar]++;
            }
        }
        arsort($votes);
        $top = array_key_first($votes);
        return $votes[$top] > 0 ? $top : 'E';
    }
};
