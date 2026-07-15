<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // assessment_series: 加 status、created_by_id；code 改為 nullable
        Schema::table('assessment_series', function (Blueprint $table) {
            $table->string('code', 100)->nullable()->change();
            $table->string('status', 20)->default('active')->after('description');
            $table->uuid('created_by_id')->nullable()->after('status');
        });

        // assessment_series_weights: 重命名 source_question_id → source_template_question_id
        Schema::table('assessment_series_weights', function (Blueprint $table) {
            $table->dropUnique('uq_series_question');
            $table->renameColumn('source_question_id', 'source_template_question_id');
        });

        Schema::table('assessment_series_weights', function (Blueprint $table) {
            $table->unique(['series_id', 'source_template_question_id'], 'uq_series_template_question');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_series', function (Blueprint $table) {
            $table->dropColumn(['status', 'created_by_id']);
        });

        Schema::table('assessment_series_weights', function (Blueprint $table) {
            $table->dropUnique('uq_series_template_question');
            $table->renameColumn('source_template_question_id', 'source_question_id');
        });

        Schema::table('assessment_series_weights', function (Blueprint $table) {
            $table->unique(['series_id', 'source_question_id'], 'uq_series_question');
        });
    }
};
