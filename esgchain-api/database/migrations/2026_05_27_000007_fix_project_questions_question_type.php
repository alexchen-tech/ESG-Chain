<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_questions', function (Blueprint $table) {
            $table->string('question_type', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_questions', function (Blueprint $table) {
            $table->enum('question_type', ['single', 'multiple', 'text', 'scale', 'boolean'])->change();
        });
    }
};
