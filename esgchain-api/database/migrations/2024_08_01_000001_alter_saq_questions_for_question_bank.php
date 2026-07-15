<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 修改 template_id 為 nullable，新增 tags + source_bank_question_id
        Schema::table('saq_questions', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->uuid('template_id')->nullable()->change();
            $table->foreign('template_id')->references('id')->on('saq_templates')->nullOnDelete();

            $table->json('tags')->nullable()->after('sasb_metric_code');
            $table->uuid('source_bank_question_id')->nullable()->after('tags')
                  ->comment('若此題目是從題庫複製的副本，指向原始題庫題目 id');
        });

        // 2. 資料遷移：現有 5 道題 → 題庫（template_id = NULL）
        //    同時為原範本建立 5 道快照副本（template_id 保留）
        $existing = DB::table('saq_questions')
            ->whereNotNull('template_id')
            ->orderBy('order')
            ->get();

        foreach ($existing as $q) {
            // 建立副本（範本專屬快照），保留 template_id
            DB::table('saq_questions')->insert([
                'id'                     => Str::uuid(),
                'template_id'            => $q->template_id,
                'category'               => $q->category,
                'question_text'          => $q->question_text,
                'question_type'          => $q->question_type,
                'options'                => $q->options,
                'weight'                 => $q->weight,
                'order'                  => $q->order,
                'is_required'            => $q->is_required,
                'sasb_topic_id'          => $q->sasb_topic_id,
                'sasb_metric_code'       => $q->sasb_metric_code,
                'tags'                   => null,
                'source_bank_question_id' => $q->id,  // 指向原始題庫題目
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            // 將原題目改為題庫題目（template_id → NULL）
            DB::table('saq_questions')
                ->where('id', $q->id)
                ->update(['template_id' => null, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // 移除副本（source_bank_question_id IS NOT NULL）
        DB::table('saq_questions')->whereNotNull('source_bank_question_id')->delete();

        // 還原題庫題目的 template_id
        $templateId = DB::table('saq_templates')->value('id');
        if ($templateId) {
            DB::table('saq_questions')
                ->whereNull('template_id')
                ->update(['template_id' => $templateId]);
        }

        Schema::table('saq_questions', function (Blueprint $table) {
            $table->dropColumn(['tags', 'source_bank_question_id']);
            $table->dropForeign(['template_id']);
            $table->uuid('template_id')->nullable(false)->change();
            $table->foreign('template_id')->references('id')->on('saq_templates')->cascadeOnDelete();
        });
    }
};
