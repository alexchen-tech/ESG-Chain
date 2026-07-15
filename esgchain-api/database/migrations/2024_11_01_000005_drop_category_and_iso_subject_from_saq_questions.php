<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saq_questions', function (Blueprint $table) {
            // iso_subject 欄位可能因為 phase1 rollback 而不存在，先檢查
            if (Schema::hasColumn('saq_questions', 'iso_subject')) {
                $table->dropIndex(['iso_subject']);
                $table->dropColumn('iso_subject');
            }
            if (Schema::hasColumn('saq_questions', 'category')) {
                $table->dropColumn('category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('saq_questions', function (Blueprint $table) {
            $table->string('category', 5)->nullable()->after('question_text');
            $table->string('iso_subject', 20)->nullable()->after('tags')->index();
        });
    }
};
