<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 重填進入審核的問卷回覆，使答案與得分一致且具有真實感
 */
class SaqResponseRefillSeeder extends Seeder
{
    // 依等級/分段決定答案分布（index → single_choice options）
    // options: 完全符合(0) 大部分符合(1) 部分符合(2) 少部分符合(3) 不符合(4)
    private const GRADE_DIST = [
        'A' => [0, 0, 0, 1, 1, 1, 1, 2, 2, 3],          // 90+：多完全/大部分
        'B' => [0, 1, 1, 1, 1, 2, 2, 2, 3, 4],           // 75-90：以大部分為主
        'C' => [1, 1, 2, 2, 2, 2, 3, 3, 4, 4],           // 60-75：中等，偏部分符合
        'D' => [2, 2, 3, 3, 3, 4, 4, 4, 4, 0],           // 45-60：多少部分/不符合
        '_' => [0, 1, 1, 2, 2, 2, 3, 3, 4, 1],           // 無分數 SAQ（submitted/returned）
    ];

    // boolean 題答「是」的比例（依等級）
    private const BOOLEAN_YES_PROB = [
        'A' => 0.90, 'B' => 0.78, 'C' => 0.62, 'D' => 0.45, '_' => 0.70,
    ];

    // 依題目關鍵字配對佐證說明
    private const EVIDENCE_MAP = [
        '溫室氣體'    => '已完成 ISO 14064-1 溫室氣體盤查並取得第三方查驗聲明，可提供最新版報告。',
        'Scope'       => '年度 Scope 1/2/3 排放量已揭露於公司 ESG 報告書，第三方機構確信度：合理確信。',
        '減排'        => '已設定 SBTi 近零科學基礎目標，2030 年 Scope 1+2 減少 42%，每季追蹤進度。',
        '再生能源'    => '屋頂光電裝置容量 1.2MW，另購買 REC 憑證，2024 年再生能源佔比達 38%。',
        '用水'        => '已建立用水量月報機制，設有節水目標（年減 5%），取水來源全數揭露於環境報告書。',
        '廢棄物'      => '持有廢棄物清除許可，危險廢棄物委託合法廠商處理，處置聯單存檔備查。',
        '強迫勞動'    => '已簽署 RBA 行為準則，每年接受第三方工廠稽核，並要求一階供應商連署承諾書。',
        '童工'        => '招募流程均核驗身分證件，所有員工年齡均已超過 16 歲，稽核記錄存檔。',
        '職業安全'    => '已通過 ISO 45001:2018 認證，最新效期至 2027 年，可提供認證書。',
        'OSH'         => '職業安全衛生管理手冊及年度訓練記錄可供查核，近三年零重大職災。',
        '董事會'      => '董事會下設永續委員會，每季向董事會報告 ESG 績效，議事錄存檔備查。',
        '反腐'        => '已實施廉潔政策，每年舉辦員工反腐訓練，舉報熱線 24 小時運作。',
        '供應鏈'      => '已建立 Tier-1 供應商 ESG 評估機制，每年對前 20 大供應商進行現場稽核。',
        '永續採購'    => '採購政策已依 ISO 20400 框架更新，高階主管批准後公告並納入供應商合約條款。',
        '合約'        => '採購框架合約已加入永續附錄，涵蓋勞工、環境及倫理要求，供應商簽署率 100%。',
        '制裁'        => '已接入 OFAC/UN/EU 制裁名單查核系統，每月自動篩查，結果存入供應商管理系統。',
        '政治'        => '每年進行供應商所在國 CPI 指數與政治穩定指標評估，結果納入採購風險分級。',
        '自然災害'    => '高風險供應商已完成 BCP 評估，備用供應商名單維持至少 2 家，並每年演練。',
        '備用'        => '已建立 3 家替代供應商，關鍵物料庫存安全天數維持 45 天以上。',
        '碳排'        => '採購標單已納入碳排放強度評分（佔比 15%），並要求 Tier-1 提供 LCA 數據。',
        '化學品'      => '已要求供應商提交 SVHC 清單及化學品安全資料表（SDS），每年更新。',
        '在地'        => '在地採購佔採購總額 62%（2024 年），已設定 70% 目標並逐年追蹤。',
        'REACH'       => '所有出口至歐盟產品已完成 REACH/SVHC 合規聲明，更新頻率符合法規要求。',
        'RoHS'        => '產品均通過 RoHS 2.0 十項有害物質檢測，檢測報告由 SGS 出具。',
    ];

    public function run(): void
    {
        $saqs = DB::table('saqs')
            ->whereIn('status', ['submitted', 'under_review', 'completed', 'reviewed', 'review_returned'])
            ->get();

        $this->command->info("重填 {$saqs->count()} 份問卷回覆...");

        foreach ($saqs as $saq) {
            // 清除舊回覆
            DB::table('saq_responses')->where('saq_id', $saq->id)->delete();

            $pqs = DB::table('project_questions')
                ->where('project_id', $saq->project_id)
                ->orderBy('order')
                ->get();

            if ($pqs->isEmpty()) {
                continue;
            }

            $grade = $saq->grade ?? '_';
            $dist  = self::GRADE_DIST[$grade] ?? self::GRADE_DIST['_'];
            $boolYes = self::BOOLEAN_YES_PROB[$grade] ?? 0.70;

            $rows = [];
            foreach ($pqs as $i => $pq) {
                // 用 saq_id + question order 做確定性種子（不依賴 PHP rand）
                $seed = crc32($saq->id . '_' . $pq->order);
                $idx  = abs($seed) % 10;

                [$answer, $answerOptions] = $this->pickAnswer($pq, $dist[$idx], $boolYes, $seed);

                $rows[] = [
                    'id'                  => (string) Str::uuid(),
                    'saq_id'              => $saq->id,
                    'question_id'         => $pq->source_bank_question_id ?? $pq->id,
                    'project_question_id' => $pq->id,
                    'answer'              => $answer,
                    'answer_options'      => $answerOptions ? json_encode($answerOptions, JSON_UNESCAPED_UNICODE) : null,
                    'evidence_note'       => $this->pickEvidence($pq->question_text, $grade, $seed),
                    'raw_score'           => null,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ];
            }

            DB::table('saq_responses')->insert($rows);
        }

        $this->command->info('完成。');
    }

    private function pickAnswer(object $pq, int $distIdx, float $boolYes, int $seed): array
    {
        $options = $pq->options ? json_decode($pq->options, true) : [];

        switch ($pq->question_type) {
            case 'boolean':
                // 用 seed 決定是/否
                $yes = (abs($seed) % 100) < ($boolYes * 100);
                return [$yes ? '是' : '否', null];

            case 'single':
            case 'single_choice':
                if (empty($options)) {
                    return ['完全符合', null];
                }
                // 5 選項：依 distIdx 決定
                $singleOpts = ['完全符合', '大部分符合', '部分符合', '少部分符合', '不符合'];
                $chosen = $singleOpts[min($distIdx, count($singleOpts) - 1)];
                // 若範本 options 不是標準 5 選項則直接用第一個
                if (!in_array($chosen, $options)) {
                    $chosen = $options[$distIdx % count($options)];
                }
                return [null, [$chosen]];

            case 'multiple':
            case 'multiple_choice':
                if (empty($options) || count($options) < 2) {
                    return [null, []];
                }
                // 依 seed 選 1-3 項
                $count = ($distIdx < 3) ? 3 : (($distIdx < 7) ? 2 : 1);
                $count = min($count, count($options));
                // 確定性選取：從 seed 開始取連續幾項
                $start   = abs($seed) % count($options);
                $chosen  = [];
                for ($k = 0; $k < $count; $k++) {
                    $chosen[] = $options[($start + $k) % count($options)];
                }
                return [null, array_values(array_unique($chosen))];

            case 'scale':
            case 'number':
                // 數值型：依 distIdx 對應合理百分比
                $pcts = [95, 85, 75, 60, 45, 35, 25, 15, 5, 70];
                return [(string) $pcts[$distIdx % count($pcts)], null];

            default:
                // text 型：佐證說明式回覆
                $texts = [
                    '貴公司已建立相關政策，每年由管理審查會議確認執行成效。',
                    '已完成內部稽核並取得第三方認證，認證書效期至 2026 年。',
                    '政策已公告於公司官網，所有員工完成知悉簽署。',
                    '目前正在導入階段，預計 2025 Q4 完成建制。',
                    '已委外第三方進行稽核，報告結論無重大缺失。',
                ];
                return [$texts[$distIdx % count($texts)], null];
        }
    }

    private function pickEvidence(string $questionText, string $grade, int $seed): ?string
    {
        // 無分數 SAQ 或低分題目有部分空白佐證
        $skipProb = match ($grade) {
            'A' => 5,  // 5% 空白
            'B' => 15,
            'C' => 25,
            'D' => 40,
            default => 20,
        };
        if ((abs($seed >> 3) % 100) < $skipProb) {
            return null;
        }

        foreach (self::EVIDENCE_MAP as $keyword => $note) {
            if (mb_strpos($questionText, $keyword) !== false) {
                return $note;
            }
        }

        // 通用佐證
        $generic = [
            '相關文件由 ESG 辦公室存檔，可於稽核時提供查驗。',
            '年度永續報告書已揭露相關資訊，版本 2024，可下載參閱。',
            '執行記錄存於公司 EHS 管理系統，可提供截圖或匯出。',
            '已取得第三方認證或查證，最新版文件可提供。',
        ];
        return $generic[abs($seed) % count($generic)];
    }
}
