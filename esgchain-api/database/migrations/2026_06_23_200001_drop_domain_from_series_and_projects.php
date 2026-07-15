<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_series', function (Blueprint $table) {
            $table->dropColumn('domain');
        });

        Schema::table('saq_projects', function (Blueprint $table) {
            $table->dropColumn('domain');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_series', function (Blueprint $table) {
            $table->string('domain')->nullable()->after('template_version_at_creation');
        });

        Schema::table('saq_projects', function (Blueprint $table) {
            $table->string('domain')->nullable()->after('template_ref_version');
        });
    }
};
