<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->integer('order')->default(0);
            $table->text('question_text');
            $table->enum('question_type', ['single', 'multiple', 'text', 'scale', 'boolean']);
            $table->json('options')->nullable();
            $table->decimal('weight', 5, 4)->nullable();
            $table->boolean('is_required')->default(true);
            $table->uuid('sasb_topic_id')->nullable();
            $table->string('sasb_metric_code', 50)->nullable();
            $table->json('tags')->nullable();
            $table->uuid('source_bank_question_id')->nullable();
            $table->uuid('source_template_question_id')->nullable();
            $table->timestamps();

            $table->index('project_id');
            $table->index('source_bank_question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_questions');
    }
};
