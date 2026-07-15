<?php

namespace Database\Seeders;

use App\Models\SAQQuestion;
use App\Models\SasbDisclosureTopic;
use Illuminate\Database\Seeder;

/**
 * ISO 20400 永續採購指引 題庫 Seeder
 *
 * ISO 20400 七大核心主題：
 * 1. 組織治理（Organizational Governance）
 * 2. 人權（Human Rights）
 * 3. 勞工實踐（Labour Practices）
 * 4. 環境（Environment）
 * 5. 公平營運（Fair Operating Practices）
 * 6. 消費者議題（Consumer Issues）
 * 7. 社區參與（Community Involvement）
 */
class QuestionBankISO20400Seeder extends Seeder
{
    public function run(): void
    {
        $questions = [

            // ── 1. 組織治理（G）──────────────────────────────
            [
                'category'      => 'G',
                'question_text' => '貴公司是否制定並公開永續採購政策（含供應商行為準則）？',
                'question_type' => 'boolean',
                'weight'        => 0.08,
                'is_required'   => true,
                'tags'          => ['G', 'ISO-組織治理'],
            ],
            [
                'category'      => 'G',
                'question_text' => '貴公司最高管理層是否設有專責 ESG 或永續採購的治理委員會？',
                'question_type' => 'boolean',
                'weight'        => 0.07,
                'is_required'   => true,
                'tags'          => ['G', 'ISO-組織治理'],
            ],
            [
                'category'      => 'G',
                'question_text' => '貴公司是否每年對供應商進行永續績效評估，並依結果採取採購決策？',
                'question_type' => 'single_choice',
                'options'       => ['每年定期執行', '不定期執行', '規劃中，尚未執行', '未執行'],
                'weight'        => 0.07,
                'is_required'   => true,
                'tags'          => ['G', 'ISO-組織治理'],
            ],
            [
                'category'      => 'G',
                'question_text' => '貴公司永續採購目標是否已納入年度營運計畫（OKR/KPI）？',
                'question_type' => 'boolean',
                'weight'        => 0.05,
                'is_required'   => false,
                'tags'          => ['G', 'ISO-組織治理'],
            ],

            // ── 2. 人權（S）──────────────────────────────────
            [
                'category'      => 'S',
                'question_text' => '貴公司是否制定人權政策，並要求供應商遵守聯合國工商與人權指導原則（UNGPs）？',
                'question_type' => 'boolean',
                'weight'        => 0.09,
                'is_required'   => true,
                'tags'          => ['S', 'ISO-人權'],
            ],
            [
                'category'      => 'S',
                'question_text' => '貴公司是否針對供應鏈中的強迫勞動及人口販賣風險進行盡職調查（Due Diligence）？',
                'question_type' => 'single_choice',
                'options'       => ['是，每年進行', '是，不定期進行', '規劃中', '否'],
                'weight'        => 0.09,
                'is_required'   => true,
                'tags'          => ['S', 'ISO-人權', '地域風險'],
            ],
            [
                'category'      => 'S',
                'question_text' => '貴公司是否禁止使用童工（未滿 15 歲），並有書面政策為證？',
                'question_type' => 'boolean',
                'weight'        => 0.10,
                'is_required'   => true,
                'tags'          => ['S', 'ISO-人權'],
            ],
            [
                'category'      => 'S',
                'question_text' => '貴公司供應鏈中是否存在高人權風險地區（如強迫勞動高風險國家）的採購？',
                'question_type' => 'boolean',
                'weight'        => 0.08,
                'is_required'   => true,
                'tags'          => ['S', 'ISO-人權', '地域風險'],
            ],

            // ── 3. 勞工實踐（S）─────────────────────────────
            [
                'category'      => 'S',
                'question_text' => '貴公司是否確保員工享有組織工會及集體談判的自由，且未有限制之情事？',
                'question_type' => 'boolean',
                'weight'        => 0.07,
                'is_required'   => true,
                'tags'          => ['S', 'ISO-勞工'],
            ],
            [
                'category'      => 'S',
                'question_text' => '貴公司過去 12 個月的員工工傷事故率（TRIR）為何？',
                'question_type' => 'single_choice',
                'options'       => ['0（零事故）', '0.01–0.5', '0.51–1.0', '1.01–3.0', '3.0 以上'],
                'weight'        => 0.08,
                'is_required'   => true,
                'tags'          => ['S', 'ISO-勞工'],
            ],
            [
                'category'      => 'S',
                'question_text' => '貴公司最低薪資是否高於當地法定最低工資？',
                'question_type' => 'single_choice',
                'options'       => ['是，高於 20% 以上', '是，略高於法定', '等同法定最低工資', '低於法定（不應選）'],
                'weight'        => 0.06,
                'is_required'   => true,
                'tags'          => ['S', 'ISO-勞工'],
            ],
            [
                'category'      => 'S',
                'question_text' => '貴公司是否提供員工職業技能培訓，年均每人受訓時數超過 8 小時？',
                'question_type' => 'boolean',
                'weight'        => 0.04,
                'is_required'   => false,
                'tags'          => ['S', 'ISO-勞工'],
            ],

            // ── 4. 環境（E）─────────────────────────────────
            [
                'category'      => 'E',
                'question_text' => '貴公司是否完成溫室氣體盤查（Scope 1 + 2），並取得第三方查驗？',
                'question_type' => 'single_choice',
                'options'       => ['已完成盤查並取得查驗', '已完成盤查但未查驗', '正在進行盤查', '尚未啟動'],
                'weight'        => 0.10,
                'is_required'   => true,
                'tags'          => ['E', 'ISO-環境'],
            ],
            [
                'category'      => 'E',
                'question_text' => '貴公司是否設定科學基礎減碳目標（SBTi）或等效減碳路徑？',
                'question_type' => 'boolean',
                'weight'        => 0.09,
                'is_required'   => true,
                'tags'          => ['E', 'ISO-環境'],
            ],
            [
                'category'      => 'E',
                'question_text' => '貴公司是否取得 ISO 14001 環境管理系統認證，或等效的環境管理認證？',
                'question_type' => 'boolean',
                'weight'        => 0.08,
                'is_required'   => true,
                'tags'          => ['E', 'ISO-環境'],
            ],
            [
                'category'      => 'E',
                'question_text' => '貴公司主要製造廠區是否使用可再生能源？若是，佔總用電比例為何？',
                'question_type' => 'single_choice',
                'options'       => ['> 50%', '10–50%', '< 10%', '未使用再生能源'],
                'weight'        => 0.07,
                'is_required'   => false,
                'tags'          => ['E', 'ISO-環境'],
            ],
            [
                'category'      => 'E',
                'question_text' => '貴公司是否制定廢棄物減量計畫，並追蹤年度廢棄物回收率？',
                'question_type' => 'boolean',
                'weight'        => 0.05,
                'is_required'   => false,
                'tags'          => ['E', 'ISO-環境'],
            ],
            [
                'category'      => 'E',
                'question_text' => '貴公司製程中是否使用受《水俁公約》或《斯德哥爾摩公約》管制的危害物質？',
                'question_type' => 'boolean',
                'weight'        => 0.06,
                'is_required'   => true,
                'tags'          => ['E', 'ISO-環境'],
            ],

            // ── 5. 公平營運（G）─────────────────────────────
            [
                'category'      => 'G',
                'question_text' => '貴公司是否制定反貪腐政策，並對所有員工進行年度訓練？',
                'question_type' => 'boolean',
                'weight'        => 0.08,
                'is_required'   => true,
                'tags'          => ['G', 'ISO-公平營運'],
            ],
            [
                'category'      => 'G',
                'question_text' => '貴公司是否建立匿名舉報機制（如吹哨者保護制度）？',
                'question_type' => 'boolean',
                'weight'        => 0.06,
                'is_required'   => true,
                'tags'          => ['G', 'ISO-公平營運'],
            ],
            [
                'category'      => 'G',
                'question_text' => '過去三年內，貴公司是否曾受到反壟斷或不公平競爭相關調查或處罰？',
                'question_type' => 'boolean',
                'weight'        => 0.07,
                'is_required'   => true,
                'tags'          => ['G', 'ISO-公平營運'],
            ],
            [
                'category'      => 'G',
                'question_text' => '貴公司是否遵守所有適用的資料保護法規（如 GDPR、個資法），並有書面政策？',
                'question_type' => 'boolean',
                'weight'        => 0.05,
                'is_required'   => false,
                'tags'          => ['G', 'ISO-公平營運'],
            ],

            // ── 6. 消費者議題（S/G）─────────────────────────
            [
                'category'      => 'S',
                'question_text' => '貴公司產品是否符合目標市場的強制性安全標準（如 CE、UL、CNS）？',
                'question_type' => 'boolean',
                'weight'        => 0.07,
                'is_required'   => true,
                'tags'          => ['S', 'ISO-消費者'],
            ],
            [
                'category'      => 'G',
                'question_text' => '貴公司是否提供清晰的產品成分/材料揭露，包含有害物質聲明（RoHS/REACH）？',
                'question_type' => 'boolean',
                'weight'        => 0.06,
                'is_required'   => true,
                'tags'          => ['G', 'ISO-消費者'],
            ],

            // ── 7. 社區參與（S）─────────────────────────────
            [
                'category'      => 'S',
                'question_text' => '貴公司是否積極僱用在地員工（廠區所在地居民佔員工比例）？',
                'question_type' => 'single_choice',
                'options'       => ['> 80%', '50–80%', '20–50%', '< 20%'],
                'weight'        => 0.05,
                'is_required'   => false,
                'tags'          => ['S', 'ISO-社區'],
            ],
            [
                'category'      => 'S',
                'question_text' => '貴公司是否設有社區申訴管道，允許廠區周邊居民反映環境或社會影響？',
                'question_type' => 'boolean',
                'weight'        => 0.05,
                'is_required'   => false,
                'tags'          => ['S', 'ISO-社區'],
            ],
            [
                'category'      => 'S',
                'question_text' => '過去一年，貴公司是否曾受到來自社區的重大環境或噪音投訴？',
                'question_type' => 'boolean',
                'weight'        => 0.04,
                'is_required'   => false,
                'tags'          => ['S', 'ISO-社區'],
            ],
        ];

        foreach ($questions as $q) {
            SAQQuestion::firstOrCreate(
                [
                    'template_id'   => null,
                    'question_text' => $q['question_text'],
                ],
                array_merge($q, [
                    'template_id'             => null,
                    'source_bank_question_id' => null,
                    'order'                   => 0,
                ])
            );
        }

        $this->command->info('ISO 20400 題庫 Seeder 完成，共 '.count($questions).' 道題目');
    }
}
