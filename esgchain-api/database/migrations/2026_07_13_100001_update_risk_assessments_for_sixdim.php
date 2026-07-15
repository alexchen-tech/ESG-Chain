<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            // 四軸欄位降為 nullable（不刪除，保留歷史）
            $table->tinyInteger('e_probability')->unsigned()->nullable()->change();
            $table->tinyInteger('e_impact')->unsigned()->nullable()->change();
            $table->tinyInteger('s_probability')->unsigned()->nullable()->change();
            $table->tinyInteger('s_impact')->unsigned()->nullable()->change();
            $table->tinyInteger('g_probability')->unsigned()->nullable()->change();
            $table->tinyInteger('g_impact')->unsigned()->nullable()->change();
            $table->tinyInteger('gp_probability')->unsigned()->nullable()->change();
            $table->tinyInteger('gp_impact')->unsigned()->nullable()->change();

            // assessment_version default 升為 v3
            $table->string('assessment_version', 10)->default('v3')->change();

            // 多型識別欄位
            $table->enum('source_type', ['saq', 'geo_event', 'regulation_change', 'manual_review'])
                ->default('saq')
                ->after('source_saq_id');
            $table->uuid('source_id')->nullable()->after('source_type');
        });

        // 回填 source_id = source_saq_id
        DB::statement("UPDATE risk_assessments SET source_id = source_saq_id WHERE source_saq_id IS NOT NULL");
    }

    public function down(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'source_id']);
            $table->tinyInteger('e_probability')->unsigned()->nullable(false)->change();
            $table->tinyInteger('e_impact')->unsigned()->nullable(false)->change();
            $table->tinyInteger('s_probability')->unsigned()->nullable(false)->change();
            $table->tinyInteger('s_impact')->unsigned()->nullable(false)->change();
            $table->tinyInteger('g_probability')->unsigned()->nullable(false)->change();
            $table->tinyInteger('g_impact')->unsigned()->nullable(false)->change();
            $table->tinyInteger('gp_probability')->unsigned()->nullable(false)->change();
            $table->tinyInteger('gp_impact')->unsigned()->nullable(false)->change();
            $table->string('assessment_version', 10)->default('legacy')->change();
        });
    }
};
