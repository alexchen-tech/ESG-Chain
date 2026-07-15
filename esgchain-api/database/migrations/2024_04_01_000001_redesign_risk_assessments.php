<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 建立新的 risk_assessments 表（5×5 矩陣法，E/S/G/GP 四維度）
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id')->index();
            // E — 環境
            $table->tinyInteger('e_probability')->unsigned()->comment('1-5');
            $table->tinyInteger('e_impact')->unsigned()->comment('1-5');
            // S — 社會
            $table->tinyInteger('s_probability')->unsigned();
            $table->tinyInteger('s_impact')->unsigned();
            // G — 治理
            $table->tinyInteger('g_probability')->unsigned();
            $table->tinyInteger('g_impact')->unsigned();
            // GP — 地緣政治（獨立第四維度）
            $table->tinyInteger('gp_probability')->unsigned()->comment('地緣政治風險概率');
            $table->tinyInteger('gp_impact')->unsigned()->comment('地緣政治風險影響');
            $table->timestamp('assessed_at');
            $table->uuid('assessed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessments');
    }
};
