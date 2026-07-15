<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saqs', function (Blueprint $table) {
            $table->decimal('score_e', 5, 2)->nullable()->after('score');
            $table->decimal('score_s', 5, 2)->nullable()->after('score_e');
            $table->decimal('score_g', 5, 2)->nullable()->after('score_s');
            $table->uuid('scoring_model_id')->nullable()->after('score_g');
            $table->decimal('final_score', 5, 2)->nullable()->after('grade');
            $table->string('final_grade', 1)->nullable()->after('final_score');
            $table->timestamp('disputed_at')->nullable()->after('reviewed_at');
        });

        // 擴充 status ENUM 加入申訴流程新狀態
        \DB::statement("ALTER TABLE saqs MODIFY status ENUM('sent','in_progress','submitted','under_review','review_returned','completed','reviewed','disputed','re_review','finalized') NOT NULL DEFAULT 'sent'");
    }

    public function down(): void
    {
        Schema::table('saqs', function (Blueprint $table) {
            $table->dropColumn(['score_e', 'score_s', 'score_g', 'scoring_model_id', 'final_score', 'final_grade', 'disputed_at']);
        });

        \DB::statement("ALTER TABLE saqs MODIFY status ENUM('sent','in_progress','submitted','under_review','review_returned','completed','reviewed') NOT NULL DEFAULT 'sent'");
    }
};
