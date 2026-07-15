<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saq_responses', function (Blueprint $table) {
            $table->uuid('project_question_id')->nullable()->after('question_id');
            $table->decimal('raw_score', 8, 4)->nullable()->after('evidence_note');
            $table->index('project_question_id');
        });
    }

    public function down(): void
    {
        Schema::table('saq_responses', function (Blueprint $table) {
            $table->dropIndex(['project_question_id']);
            $table->dropColumn(['project_question_id', 'raw_score']);
        });
    }
};
