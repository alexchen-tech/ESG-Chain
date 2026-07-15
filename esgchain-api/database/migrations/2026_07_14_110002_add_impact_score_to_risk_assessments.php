<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            // 建立評估當下的 Impact 快照（point-in-time），供 heatmap before_days 歷史回溯。
            $table->tinyInteger('impact_score')->nullable()->after('assessment_version')
                ->comment('評估建立當下的 supplier.impact_score 快照（1–5）');
        });
    }

    public function down(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->dropColumn('impact_score');
        });
    }
};
