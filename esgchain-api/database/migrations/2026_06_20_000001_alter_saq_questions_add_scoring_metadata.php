<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saq_questions', function (Blueprint $table) {
            $table->enum('scoring_direction', ['positive', 'negative'])->default('positive')->after('is_required');
            $table->string('scoring_type', 20)->nullable()->after('scoring_direction');
            $table->json('option_scores')->nullable()->after('scoring_type');
        });
    }

    public function down(): void
    {
        Schema::table('saq_questions', function (Blueprint $table) {
            $table->dropColumn(['scoring_direction', 'scoring_type', 'option_scores']);
        });
    }
};
