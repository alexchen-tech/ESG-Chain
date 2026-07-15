<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ISO_SUBJECTS = [
        '組織治理', '人權', '勞工', '環境', '公平營運', '消費者', '社區',
    ];

    private const TAG_TO_SUBJECT = [
        'ISO-組織治理' => '組織治理',
        'ISO-人權'     => '人權',
        'ISO-勞工'     => '勞工',
        'ISO-環境'     => '環境',
        'ISO-公平營運' => '公平營運',
        'ISO-消費者'   => '消費者',
        'ISO-社區'     => '社區',
    ];

    public function up(): void
    {
        Schema::table('saq_questions', function (Blueprint $table) {
            $table->string('iso_subject', 20)->nullable()->after('tags')->index();
        });

        // 資料遷移：tags 中的 ISO-xxx → iso_subject，並清理 tags
        $questions = DB::table('saq_questions')
            ->whereNull('template_id')  // 只遷移題庫題目
            ->whereNotNull('tags')
            ->get();

        foreach ($questions as $q) {
            $tags = json_decode($q->tags, true) ?? [];
            if (empty($tags)) continue;

            $isoSubject = null;
            $cleanedTags = [];

            foreach ($tags as $tag) {
                if (isset(self::TAG_TO_SUBJECT[$tag])) {
                    $isoSubject = self::TAG_TO_SUBJECT[$tag];
                    // 不加入 cleanedTags（移除）
                } elseif (in_array($tag, ['E', 'S', 'G'])) {
                    // 移除 ESG tags（category 已有）
                } else {
                    $cleanedTags[] = $tag;
                }
            }

            DB::table('saq_questions')
                ->where('id', $q->id)
                ->update([
                    'iso_subject' => $isoSubject,
                    'tags'        => empty($cleanedTags) ? null : json_encode($cleanedTags, JSON_UNESCAPED_UNICODE),
                    'updated_at'  => now(),
                ]);
        }
    }

    public function down(): void
    {
        // 反向：iso_subject → 加回 tags，然後移除欄位
        $questions = DB::table('saq_questions')
            ->whereNull('template_id')
            ->whereNotNull('iso_subject')
            ->get();

        $subjectToTag = array_flip(self::TAG_TO_SUBJECT);

        foreach ($questions as $q) {
            $tags = json_decode($q->tags ?? '[]', true) ?? [];
            if (isset($subjectToTag[$q->iso_subject])) {
                $tags[] = $subjectToTag[$q->iso_subject];
            }
            DB::table('saq_questions')
                ->where('id', $q->id)
                ->update([
                    'tags'       => empty($tags) ? null : json_encode($tags, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        }

        Schema::table('saq_questions', function (Blueprint $table) {
            $table->dropIndex(['iso_subject']);
            $table->dropColumn('iso_subject');
        });
    }
};
