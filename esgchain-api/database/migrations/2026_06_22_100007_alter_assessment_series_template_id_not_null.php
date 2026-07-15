<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_series', function (Blueprint $table) {
            // 先移除 nullOnDelete FK，改為 restrictOnDelete，再加 NOT NULL
            $table->dropForeign(['template_id']);
        });

        Schema::table('assessment_series', function (Blueprint $table) {
            $table->char('template_id', 36)->nullable(false)->change();
            $table->foreign('template_id')
                  ->references('id')
                  ->on('saq_templates')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_series', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
        });
        Schema::table('assessment_series', function (Blueprint $table) {
            $table->char('template_id', 36)->nullable()->change();
            $table->foreign('template_id')
                  ->references('id')
                  ->on('saq_templates')
                  ->nullOnDelete();
        });
    }
};
