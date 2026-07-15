<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    // ESG Pillar → slug prefix mapping
    private const CATEGORY_TO_PILLAR = [
        'E' => ['l2_pillar' => 'E-環境', 'slug_prefix' => 'esg.e', 'label_zh' => 'E-環境', 'label_en' => 'E-Environment'],
        'S' => ['l2_pillar' => 'S-社會', 'slug_prefix' => 'esg.s', 'label_zh' => 'S-社會', 'label_en' => 'S-Social'],
        'G' => ['l2_pillar' => 'G-治理', 'slug_prefix' => 'esg.g', 'label_zh' => 'G-治理', 'label_en' => 'G-Governance'],
    ];

    // ISO 26000 七大主題 → slug mapping
    private const ISO_TO_TAG = [
        '組織治理' => ['l2_pillar' => '組織治理', 'slug' => 'iso20400.org_gov.general',   'label_zh' => '組織治理', 'label_en' => 'Organizational Governance'],
        '人權'     => ['l2_pillar' => '人權',     'slug' => 'iso20400.human_rights.general', 'label_zh' => '人權',     'label_en' => 'Human Rights'],
        '勞工'     => ['l2_pillar' => '勞工',     'slug' => 'iso20400.labor.general',        'label_zh' => '勞工',     'label_en' => 'Labor Practices'],
        '環境'     => ['l2_pillar' => '環境',     'slug' => 'iso20400.environment.general',  'label_zh' => '環境',     'label_en' => 'Environment'],
        '公平營運' => ['l2_pillar' => '公平營運', 'slug' => 'iso20400.fair_ops.general',     'label_zh' => '公平營運', 'label_en' => 'Fair Operating Practices'],
        '消費者'   => ['l2_pillar' => '消費者',   'slug' => 'iso20400.consumer.general',     'label_zh' => '消費者',   'label_en' => 'Consumer Issues'],
        '社區'     => ['l2_pillar' => '社區',     'slug' => 'iso20400.community.general',    'label_zh' => '社區',     'label_en' => 'Community Involvement'],
    ];

    public function up(): void
    {
        // 1. 確保 ESG Pillar 的 L2-level tag 存在（作為遷移用的通用 L3 tag）
        foreach (self::CATEGORY_TO_PILLAR as $cat => $pillar) {
            $slug = $pillar['slug_prefix'] . '.general';
            if (!DB::table('question_tags')->where('slug', $slug)->exists()) {
                DB::table('question_tags')->insert([
                    'id'         => Str::uuid(),
                    'l1_domain'  => 'ESG',
                    'l2_pillar'  => $pillar['l2_pillar'],
                    'l3_topic'   => 'General',
                    'slug'       => $slug,
                    'label_zh'   => $pillar['label_zh'],
                    'label_en'   => $pillar['label_en'],
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. 確保 ISO20400 tag 存在
        foreach (self::ISO_TO_TAG as $subject => $tag) {
            if (!DB::table('question_tags')->where('slug', $tag['slug'])->exists()) {
                DB::table('question_tags')->insert([
                    'id'         => Str::uuid(),
                    'l1_domain'  => 'ISO20400',
                    'l2_pillar'  => $tag['l2_pillar'],
                    'l3_topic'   => 'General',
                    'slug'       => $tag['slug'],
                    'label_zh'   => $tag['label_zh'],
                    'label_en'   => $tag['label_en'],
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 3. 遷移題庫題目的 category → tag_assignments
        $bankQuestions = DB::table('saq_questions')->whereNull('template_id')->get();

        foreach ($bankQuestions as $q) {
            // category (E/S/G) → ESG tag
            if (!empty($q->category) && isset(self::CATEGORY_TO_PILLAR[$q->category])) {
                $slug = self::CATEGORY_TO_PILLAR[$q->category]['slug_prefix'] . '.general';
                $tag = DB::table('question_tags')->where('slug', $slug)->first();
                if ($tag && !DB::table('question_tag_assignments')
                    ->where('question_id', $q->id)->where('tag_id', $tag->id)->exists()) {
                    DB::table('question_tag_assignments')->insert([
                        'question_id' => $q->id,
                        'tag_id'      => $tag->id,
                    ]);
                }
            }

            // iso_subject → ISO20400 tag（欄位可能不存在，用 try/catch 保護）
            try {
                if (!empty($q->iso_subject) && isset(self::ISO_TO_TAG[$q->iso_subject])) {
                    $slug = self::ISO_TO_TAG[$q->iso_subject]['slug'];
                    $tag = DB::table('question_tags')->where('slug', $slug)->first();
                    if ($tag && !DB::table('question_tag_assignments')
                        ->where('question_id', $q->id)->where('tag_id', $tag->id)->exists()) {
                        DB::table('question_tag_assignments')->insert([
                            'question_id' => $q->id,
                            'tag_id'      => $tag->id,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // iso_subject 欄位不存在時略過
            }
        }

        // 4. 複製快照副本的 tag_assignments（從 source_bank_question_id 繼承）
        $snapshots = DB::table('saq_questions')
            ->whereNotNull('template_id')
            ->whereNotNull('source_bank_question_id')
            ->get();

        foreach ($snapshots as $snap) {
            $bankAssignments = DB::table('question_tag_assignments')
                ->where('question_id', $snap->source_bank_question_id)
                ->get();

            foreach ($bankAssignments as $assignment) {
                if (!DB::table('question_tag_assignments')
                    ->where('question_id', $snap->id)
                    ->where('tag_id', $assignment->tag_id)
                    ->exists()) {
                    DB::table('question_tag_assignments')->insert([
                        'question_id' => $snap->id,
                        'tag_id'      => $assignment->tag_id,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // 移除所有由此 migration 建立的 assignments（無法區分哪些是手動建立，全清）
        DB::table('question_tag_assignments')->delete();
        DB::table('question_tags')
            ->whereIn('slug', array_merge(
                array_map(fn($p) => $p['slug_prefix'] . '.general', self::CATEGORY_TO_PILLAR),
                array_column(self::ISO_TO_TAG, 'slug')
            ))
            ->delete();
    }
};
