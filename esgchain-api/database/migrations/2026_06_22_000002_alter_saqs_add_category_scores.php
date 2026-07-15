<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saqs', function (Blueprint $table) {
            $table->json('category_scores')->nullable()->after('score_g')
                ->comment('框架 L2 pillar 維度分數，如 {"採購政策": 72.3, "績效評估": 85.1}');
        });
    }

    public function down(): void
    {
        Schema::table('saqs', function (Blueprint $table) {
            $table->dropColumn('category_scores');
        });
    }
};
