<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComplianceDomainAssignmentSeeder extends Seeder
{
    /**
     * 合規範疇關鍵字映射
     * 符合任一關鍵字即標記對應範疇
     */
    private const DOMAIN_KEYWORDS = [
        'UFLPA' => [
            '強迫勞動', '童工', 'UFLPA', '棉花', '新疆',
            '強制勞動', '移工', '人身自由', '扣押文件',
            '人力仲介', '招募費', '債務奴役',
        ],
        'EUDR' => [
            'EUDR', '森林', 'FSC', '木材', '毀林',
            '大豆', '可可', '牛肉', '咖啡', '棕櫚',
            '橡膠', '原料溯源', '可追溯性',
        ],
        'CMRT' => [
            'CMRT', '衝突礦物', '錫', '鉭', '鎢',
            '3TG', 'DRC', '剛果', '礦物溯源', '衝突地區',
        ],
        'SDS' => [
            'SDS', '化學品', 'REACH', 'RoHS', '有害物質',
            'SVHC', '化學合規', '化學管制', '危化',
            '危險化學', '有害化學', '禁用物質',
        ],
        'CE' => [
            'CE認證', 'ESPR', '產品安全', 'LVD', 'ErP',
            '電氣安全', '電磁相容', 'EMC', '低電壓指令',
            '符合性聲明', 'DoC', '歐盟產品合規',
        ],
    ];

    public function run(): void
    {
        $questions = DB::table('saq_questions')->get(['id', 'question_text', 'tags']);

        $updated = 0;

        foreach ($questions as $q) {
            $tags = is_string($q->tags) ? json_decode($q->tags, true) : ($q->tags ?? []);
            $combined = $q->question_text . ' ' . implode(' ', (array) $tags);

            $domains = [];
            foreach (self::DOMAIN_KEYWORDS as $domain => $keywords) {
                foreach ($keywords as $kw) {
                    if (str_contains($combined, $kw)) {
                        $domains[] = $domain;
                        break;
                    }
                }
            }

            if (!empty($domains)) {
                DB::table('saq_questions')
                    ->where('id', $q->id)
                    ->update(['compliance_domains' => json_encode($domains, JSON_UNESCAPED_UNICODE)]);
                $updated++;

                $this->command->line("  ✓ [{$q->id}] " . mb_substr($q->question_text, 0, 40) . ' → ' . implode(', ', $domains));
            }
        }

        $this->command->info("合規範疇標記完成：共更新 {$updated} / " . $questions->count() . " 題");
    }
}
