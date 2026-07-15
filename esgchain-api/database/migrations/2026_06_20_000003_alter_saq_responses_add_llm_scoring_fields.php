<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saq_responses', function (Blueprint $table) {
            $table->decimal('llm_score', 5, 2)->nullable()->after('raw_score');
            $table->text('llm_score_reason')->nullable()->after('llm_score');
            $table->enum('score_confidence', ['high', 'medium', 'low'])->nullable()->after('llm_score_reason');
        });
    }

    public function down(): void
    {
        Schema::table('saq_responses', function (Blueprint $table) {
            $table->dropColumn(['llm_score', 'llm_score_reason', 'score_confidence']);
        });
    }
};
