<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_questions', function (Blueprint $table) {
            $table->boolean('is_sasb_required')->default(false)->after('source_template_question_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_questions', function (Blueprint $table) {
            $table->dropColumn('is_sasb_required');
        });
    }
};
