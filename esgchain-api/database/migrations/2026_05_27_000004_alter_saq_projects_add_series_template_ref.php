<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saq_projects', function (Blueprint $table) {
            $table->uuid('series_id')->nullable()->after('domain');
            $table->uuid('template_ref_id')->nullable()->after('series_id');
            $table->integer('template_ref_version')->nullable()->after('template_ref_id');
        });
    }

    public function down(): void
    {
        Schema::table('saq_projects', function (Blueprint $table) {
            $table->dropColumn(['series_id', 'template_ref_id', 'template_ref_version']);
        });
    }
};
