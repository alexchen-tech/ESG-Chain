<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // ESG-Chain 擁有：風險矩陣 Impact 軸四因子加權計分（1–5）正本。
            // ⚠️ ERP sync 不可覆蓋此欄位，僅透過 ImpactScoreService 重算更新。
            $table->tinyInteger('impact_score')->nullable()->after('risk_score')
                ->comment('風險矩陣 Impact 值 1–5（四因子加權，ESG-Chain 擁有，ERP 不可覆蓋）');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('impact_score');
        });
    }
};
