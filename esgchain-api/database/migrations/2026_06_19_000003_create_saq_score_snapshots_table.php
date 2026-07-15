<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saq_score_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('saq_id');
            $table->decimal('score', 5, 2);
            $table->string('grade', 1);
            $table->decimal('score_e', 5, 2)->nullable();
            $table->decimal('score_s', 5, 2)->nullable();
            $table->decimal('score_g', 5, 2)->nullable();
            $table->uuid('scoring_model_id')->nullable()->comment('null 表示使用預設模型');
            $table->enum('trigger', ['submit', 'weight_updated', 'reviewer_override', 're_review']);
            $table->uuid('triggered_by')->nullable()->comment('AI 自動計分時為 null');
            $table->timestamp('scored_at');
            $table->timestamps();

            $table->foreign('saq_id')->references('id')->on('saqs')->cascadeOnDelete();
            $table->index(['saq_id', 'scored_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saq_score_snapshots');
    }
};
