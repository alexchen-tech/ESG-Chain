<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 補齊 Geo-Risk 範本 bank questions 的 geo_risk.* TAG 指派（33 道題）。
 */
return new class extends Migration
{
    private array $mappings = [
        // ISO 28000 / 資安 / BCP 類
        '255d947f-ff3d-46fb-8b8c-35ebe2be54b9' => 'geo_risk.logistics.bcp',   // 防火牆 IDS/IPS
        'd0008782-f770-419e-81ad-74281797d27c' => 'geo_risk.logistics.bcp',   // 資安事件通報應變
        'e69b452e-42dd-46e2-aa49-40e348f1471e' => 'geo_risk.logistics.bcp',   // 資料備援
        '23f60916-2779-4dfb-a403-59032a4e9717' => 'geo_risk.logistics.bcp',   // 貨物封條 ISO 17712
        'c6277bd1-a9c4-4992-b72f-9b956b33237f' => 'geo_risk.logistics.bcp',   // ISO 28000 / C-TPAT 認證
        '914e6c16-9ee1-44d5-965b-8d009f7dc5eb' => 'geo_risk.logistics.single_src', // 物流供應商年度評估
        '5b9aa6a0-4c4d-4453-a10a-093efafbfdd2' => 'geo_risk.logistics.bcp',   // 出入口監控系統
        'a2990830-a06e-41d2-b344-ec01217f0860' => 'geo_risk.logistics.bcp',   // 安全管理內部稽核
        '73b6327d-dc02-4128-bc73-62b4ac66cdf5' => 'geo_risk.logistics.bcp',   // 高層安全管理審查
        '95fb2148-e053-4444-9468-66d6e02f9787' => 'geo_risk.logistics.bcp',   // LTIFR
        'c736f0ad-cb07-4616-9b27-bc827c93cf9b' => 'geo_risk.logistics.bcp',   // 職場健康安全預防
        // 地緣政治 / 制裁 / 合規類
        'f795d6ee-023e-47a0-8d25-183a18b35bc3' => 'geo_risk.geopo.sanctions', // 供應商盡職調查
        'eb3e17e7-8a26-4667-a848-129e7f1b3502' => 'geo_risk.geopo.sanctions', // 強迫勞動風險
        '6393a763-2e22-4f4c-a805-678b81970551' => 'geo_risk.geopo.sanctions', // 反腐敗政策
        '1143b50b-bf83-4c9e-bc8f-06d215e6adba' => 'geo_risk.geopo.sanctions', // 背景查核
        '428485ed-3704-4346-bc42-d112d7a32d02' => 'geo_risk.geopo.sanctions', // 智財地區保護
        'b223605c-5e6f-4717-8c9e-58cb3ae7dece' => 'geo_risk.logistics.bcp',   // 永續認證
        'a29f203d-3ef3-4510-bb67-5e6b9fa59434' => 'geo_risk.logistics.bcp',   // ESG認證說明
        // 衝突 / 情資 / 危機類
        '7d17e497-6741-47ce-8166-dd386cb688e5' => 'geo_risk.geopo.conflict',  // 女性比例（社會穩定）
        'f025150c-1e64-41a5-9de5-0454c09b403a' => 'geo_risk.geopo.conflict',  // 危機模擬演練
        '7564da94-f56e-4f69-bb93-5841e4713fea' => 'geo_risk.geopo.conflict',  // 情資資訊取得
        'c4f38f02-4522-4016-99c5-7be447387100' => 'geo_risk.geopo.conflict',  // 緊急通訊矩陣
        '91a2a036-a235-4d71-9fc1-0b8c9efff490' => 'geo_risk.geopo.conflict',  // 大規模中斷決策
        // 單一來源 / 集中採購 類
        '5300052b-4f01-4465-a9cd-a4b95fb8241d' => 'geo_risk.logistics.single_src', // 單一國家採購減壓
        '26272e74-e1b0-40b0-bac3-8bcef2d389ac' => 'geo_risk.logistics.single_src', // 不同地緣板塊量產
        // 環境 / 氣候 / 資源韌性 類
        '4dcd7499-44cf-4229-8b4c-52ee12153f37' => 'geo_risk.logistics.disaster',  // Scope 1 GHG
        '44655e5a-69a9-43cf-b3dd-38ef83180112' => 'geo_risk.logistics.disaster',  // 用水量
        '78f3a6bd-2402-4dc1-a449-0dd592cb3442' => 'geo_risk.logistics.disaster',  // 溫室氣體減量
        'af9de116-6b9b-4dfe-937b-4b1f8919df2e' => 'geo_risk.logistics.disaster',  // 用電量
        '7f343a67-975c-41f8-a02d-422df0b4c241' => 'geo_risk.logistics.disaster',  // 再生能源
        '10d078e0-a4fe-4948-86db-c51703ca36d6' => 'geo_risk.logistics.disaster',  // 水資源管理
        '3c7353f7-5f25-4224-8a91-d1bf5cb4855d' => 'geo_risk.logistics.disaster',  // 備電應急
        'f30c5298-c967-4b7e-a64d-9c9f4c444764' => 'geo_risk.logistics.disaster',  // 廢棄物處理
    ];

    public function up(): void
    {
        foreach ($this->mappings as $questionId => $tagSlug) {
            $tagId = DB::table('question_tags')->where('slug', $tagSlug)->value('id');
            if (!$tagId) continue;

            DB::table('question_tag_assignments')->insertOrIgnore([
                'question_id' => $questionId,
                'tag_id'      => $tagId,
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->mappings as $questionId => $tagSlug) {
            $tagId = DB::table('question_tags')->where('slug', $tagSlug)->value('id');
            if (!$tagId) continue;

            DB::table('question_tag_assignments')
                ->where('question_id', $questionId)
                ->where('tag_id', $tagId)
                ->delete();
        }
    }
};
