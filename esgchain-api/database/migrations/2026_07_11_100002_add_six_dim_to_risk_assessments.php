<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->decimal('dim_e1', 5, 2)->nullable()->after('ai_generated_at')->comment('ESG整體維度分數 0-100');
            $table->decimal('dim_e2', 5, 2)->nullable()->after('dim_e1')->comment('ISO 20400採購永續維度分數');
            $table->decimal('dim_e3', 5, 2)->nullable()->after('dim_e2')->comment('ISO 26000社會責任維度分數');
            $table->decimal('dim_e4', 5, 2)->nullable()->after('dim_e3')->comment('地緣風險維度分數（混合計分）');
            $table->decimal('dim_e5', 5, 2)->nullable()->after('dim_e4')->comment('ISO 28000供應鏈安全維度分數');
            $table->decimal('dim_e6', 5, 2)->nullable()->after('dim_e5')->comment('產品合規維度分數（混合計分）');
            $table->string('assessment_version', 10)->default('legacy')->after('dim_e6')->comment('legacy=舊版三軸, v2=新版六維');
        });
    }

    public function down(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->dropColumn(['dim_e1', 'dim_e2', 'dim_e3', 'dim_e4', 'dim_e5', 'dim_e6', 'assessment_version']);
        });
    }
};
