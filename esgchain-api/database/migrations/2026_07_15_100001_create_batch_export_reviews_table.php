<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_export_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('production_batch_id')->index()->comment('生產批次');
            $table->string('market', 10)->comment('目標市場代碼（EU/US/UK/JP/GLOBAL）');
            $table->enum('status', ['pending', 'pass', 'warning', 'fail'])->default('pending')->comment('審查結論');
            $table->json('findings')->nullable()->comment('逐項檢核結果（文件/EUDR溯源/UFLPA/PCF/DPP）');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // 一批可多市場；每「批次×市場」僅一筆（重跑 upsert）
            $table->unique(['production_batch_id', 'market']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_export_reviews');
    }
};
