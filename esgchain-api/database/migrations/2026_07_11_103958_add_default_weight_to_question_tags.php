<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_tags', function (Blueprint $table) {
            // L2 節點（l3_topic='General', l2_pillar!='General'）才有值，L1/L3 為 NULL
            $table->decimal('default_weight', 5, 4)->nullable()->after('scoring_engine_key');
        });
    }

    public function down(): void
    {
        Schema::table('question_tags', function (Blueprint $table) {
            $table->dropColumn('default_weight');
        });
    }
};
