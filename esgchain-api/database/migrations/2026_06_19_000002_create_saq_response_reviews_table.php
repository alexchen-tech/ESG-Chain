<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saq_response_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('saq_id');
            $table->uuid('project_question_id');
            $table->uuid('reviewer_id');
            $table->decimal('reviewer_score', 5, 2)->comment('0–100');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['saq_id', 'project_question_id']);
            $table->foreign('saq_id')->references('id')->on('saqs')->cascadeOnDelete();
            $table->foreign('project_question_id')->references('id')->on('project_questions')->cascadeOnDelete();
            $table->foreign('reviewer_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saq_response_reviews');
    }
};
