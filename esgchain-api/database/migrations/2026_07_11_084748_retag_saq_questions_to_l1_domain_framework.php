<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 將 saq_questions.tags 從舊格式（E-code + 單字母 pillar / 字串陣列）
 * 統一轉為新格式：{framework: l1_domain, pillar: L2_pillar_slug, weight: 1.0}
 *
 * 轉換規則：
 *   新格式 E1|E → ESG | esg.env
 *   新格式 E1|S → ESG | esg.soc
 *   新格式 E1|G → ESG | esg.gov
 *   舊字串格式  → 依題目關鍵字推斷 framework + pillar
 */
return new class extends Migration
{
    /** E1 pillar 字母 → ESG L2 pillar_slug 對照 */
    private array $e1PillarMap = [
        'E' => 'esg.env',
        'S' => 'esg.soc',
        'G' => 'esg.gov',
    ];

    /**
     * 關鍵字 → [framework, pillar]
     * 按 ISO28000 / Geo-Risk / ISO20400 / ISO26000 / Product-Compliance / ESG 順序比對。
     */
    private array $keywordRules = [
        // ISO28000 - 實體與人員安全
        ['/門禁|監視|攝影機|背景查核|人員安全|身份識別/u', 'ISO28000', 'iso28k.physical'],
        // ISO28000 - 認證與管理體系
        ['/C-TPAT|AEO|ISO.?28000|內部稽核|管理審查/u', 'ISO28000', 'iso28k.cert'],
        // ISO28000 - 貨物與物流安全
        ['/貨物|封條|ISO.?17712|GPS|物流供應商|裝卸/u', 'ISO28000', 'iso28k.cargo'],
        // ISO28000 - 資訊安全與韌性
        ['/資安|BCP|EPRP|緊急應變|War.Gaming|危機演練|韌性|備援|業務持續/u', 'ISO28000', 'iso28k.infosec'],
        // Geo-Risk - 政治風險
        ['/制裁|OFAC|衝突礦物|地緣政治|風險監控|出口管制/u', 'Geo-Risk', 'georisk.political'],
        // Geo-Risk - 環境風險（地緣分散）
        ['/地緣分散|多元採購|供應鏈分散/u', 'Geo-Risk', 'georisk.environmental'],
        // Geo-Risk - 社會風險
        ['/社會穩定|勞資|勞動抗議/u', 'Geo-Risk', 'georisk.social'],
        // Geo-Risk - 法規風險
        ['/關稅|貿易壁壘|貿易法規/u', 'Geo-Risk', 'georisk.regulatory'],
        // ISO20400 - 採購政策
        ['/永續採購|採購政策|採購評選|承諾書|採購承諾/u', 'ISO20400', 'iso20400.policy'],
        // ISO20400 - 報告揭露
        ['/KPI|稽核|查核|績效|報告揭露|採購報告/u', 'ISO20400', 'iso20400.reporting'],
        // ISO20400 - 盡職調查
        ['/盡職調查|Due.Diligence|人權.*風險|環境.*風險|採購.*風險/u', 'ISO20400', 'iso20400.due_diligence'],
        // ISO20400 - 能力建構
        ['/供應商能力|能力建構|供應商培訓/u', 'ISO20400', 'iso20400.capacity'],
        // ISO26000 - 勞工實踐
        ['/勞工|童工|強迫勞動|工時|工資|結社自由|歧視/u', 'ISO26000', 'iso26000.labor'],
        // ISO26000 - 組織治理
        ['/治理|董事|課責|決策流程/u', 'ISO26000', 'iso26000.governance'],
        // ISO26000 - 公平營運
        ['/智財|反壟斷|競爭法|公平競爭|反腐|賄賂/u', 'ISO26000', 'iso26000.fairop'],
        // ISO26000 - 社區參與
        ['/利害關係|溝通|社區|社會影響/u', 'ISO26000', 'iso26000.community'],
        // Product-Compliance - CBAM
        ['/CBAM|碳邊境|內含碳/u', 'Product-Compliance', 'prod_comp.cbam'],
        // Product-Compliance - EUDR
        ['/EUDR|去森林|DDS|FSC|PEFC/u', 'Product-Compliance', 'prod_comp.eudr'],
        // Product-Compliance - 化學法規
        ['/REACH|RoHS|SVHC|Azo|甲醛|Prop.65|PFAS|化學品|有害物質/u', 'Product-Compliance', 'prod_comp.chem'],
        // Product-Compliance - 溯源與認證
        ['/UFLPA|CMRT|原產地|溯源/u', 'Product-Compliance', 'prod_comp.trace'],
        // ESG - 環境
        ['/廢棄物|溫室氣體|能源|用水|碳排|生物多樣|碳中和|循環/u', 'ESG', 'esg.env'],
        // ESG - 社會
        ['/職安|PPE|安全訓練|社區投資|產品安全|資料隱私/u', 'ESG', 'esg.soc'],
        // ESG - 治理
        ['/行為準則|吹哨|透明度|供應商行為準則|技術安全/u', 'ESG', 'esg.gov'],
    ];

    public function up(): void
    {
        $questions = DB::table('saq_questions')->get(['id', 'question_text', 'tags']);

        foreach ($questions as $q) {
            $tags = json_decode($q->tags, true);
            if (!is_array($tags) || empty($tags)) {
                continue;
            }

            $newTags = null;

            // 情況一：已是 new_object 格式（含 E-code 或 L1 domain）
            if (isset($tags[0]['framework'])) {
                $newTags = array_map(function ($tag) {
                    $fw = $tag['framework'] ?? '';
                    $pillar = $tag['pillar'] ?? '';

                    $fwMap = [
                        'E1' => 'ESG', 'E2' => 'ISO20400', 'E3' => 'ISO26000',
                        'E4' => 'Geo-Risk', 'E5' => 'ISO28000', 'E6' => 'Product-Compliance',
                    ];
                    $newFw = $fwMap[$fw] ?? $fw;

                    // E1 的 E/S/G 單字母轉為 L2 pillar_slug
                    if ($fw === 'E1') {
                        $pillar = $this->e1PillarMap[$pillar] ?? 'esg.env';
                    }

                    return ['framework' => $newFw, 'pillar' => $pillar, 'weight' => $tag['weight'] ?? 1.0];
                }, $tags);
            }

            // 情況二：舊字串陣列格式，依關鍵字推斷
            elseif (is_string($tags[0])) {
                $text = $q->question_text . ' ' . implode(' ', $tags);
                [$fw, $pillar] = $this->inferFrameworkPillar($text);
                $newTags = [['framework' => $fw, 'pillar' => $pillar, 'weight' => 1.0]];
            }

            if ($newTags !== null) {
                $domains = array_values(array_unique(array_column($newTags, 'framework')));
                DB::table('saq_questions')->where('id', $q->id)->update([
                    'tags'               => json_encode($newTags, JSON_UNESCAPED_UNICODE),
                    'compliance_domains' => json_encode($domains, JSON_UNESCAPED_UNICODE),
                    'updated_at'         => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // 資料遷移不可逆
    }

    private function inferFrameworkPillar(string $text): array
    {
        foreach ($this->keywordRules as [$pattern, $fw, $pillar]) {
            if (preg_match($pattern, $text)) {
                return [$fw, $pillar];
            }
        }
        return ['ESG', 'esg.env'];
    }
};
