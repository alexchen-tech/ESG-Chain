<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_series', function (Blueprint $table) {
            $table->json('pillar_weights')->nullable()->after('template_version_at_creation');
            $table->json('grade_thresholds')->nullable()->after('pillar_weights');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_series', function (Blueprint $table) {
            $table->dropColumn(['pillar_weights', 'grade_thresholds']);
        });
    }
};
