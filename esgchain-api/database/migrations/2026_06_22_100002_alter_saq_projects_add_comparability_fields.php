<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saq_projects', function (Blueprint $table) {
            $table->boolean('is_comparable')->default(true)->after('series_id');
            $table->string('template_version', 20)->nullable()->after('is_comparable');
        });
    }

    public function down(): void
    {
        Schema::table('saq_projects', function (Blueprint $table) {
            $table->dropColumn(['is_comparable', 'template_version']);
        });
    }
};
