<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->text('ai_suggestion')->nullable()->after('axis2_source_saq_id');
            $table->timestamp('ai_generated_at')->nullable()->after('ai_suggestion');
        });
    }

    public function down(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->dropColumn(['ai_suggestion', 'ai_generated_at']);
        });
    }
};
