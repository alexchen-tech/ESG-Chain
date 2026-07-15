<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_series', function (Blueprint $table) {
            $table->char('template_id', 36)->nullable()->after('domain');
            $table->string('template_version_at_creation', 20)->nullable()->after('template_id');

            $table->foreign('template_id')
                  ->references('id')
                  ->on('saq_templates')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_series', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn(['template_id', 'template_version_at_creation']);
        });
    }
};
