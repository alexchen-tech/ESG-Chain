<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_series', function (Blueprint $table) {
            $table->json('dim_weights')->nullable()->after('grade_thresholds');
            $table->enum('dim_weights_source', ['default', 'custom'])->default('default')->after('dim_weights');
            $table->decimal('e4_objective_ratio', 3, 2)->default(0.40)->after('dim_weights_source');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_series', function (Blueprint $table) {
            $table->dropColumn(['dim_weights', 'dim_weights_source', 'e4_objective_ratio']);
        });
    }
};
