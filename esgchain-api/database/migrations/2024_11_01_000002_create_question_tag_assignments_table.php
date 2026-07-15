<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_tag_assignments', function (Blueprint $table) {
            $table->uuid('question_id');
            $table->uuid('tag_id');

            $table->primary(['question_id', 'tag_id']);
            $table->foreign('question_id')->references('id')->on('saq_questions')->cascadeOnDelete();
            $table->foreign('tag_id')->references('id')->on('question_tags')->restrictOnDelete();

            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_tag_assignments');
    }
};
