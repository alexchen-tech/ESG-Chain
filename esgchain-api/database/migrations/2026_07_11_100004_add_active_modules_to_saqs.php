<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saqs', function (Blueprint $table) {
            // 發送時快照的加掛維度清單，如 ["E1","E4","E2","E5"]
            $table->json('active_modules')->nullable()->after('scoring_job_id')
                ->comment('發送時依產業分類+法規確定的作用維度清單快照');
            // 發送時供應商適用法規快照（用於 E6 動態篩題）
            $table->json('regulations_snapshot')->nullable()->after('active_modules')
                ->comment('發送時 SalesProduct applicable_regulations 快照');
        });
    }

    public function down(): void
    {
        Schema::table('saqs', function (Blueprint $table) {
            $table->dropColumn(['active_modules', 'regulations_snapshot']);
        });
    }
};
