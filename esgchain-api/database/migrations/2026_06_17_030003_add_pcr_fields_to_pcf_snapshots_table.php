<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pcf_snapshots', function (Blueprint $table) {
            $table->decimal('pcr_ratio', 5, 4)->nullable()->after('iso14067_ready');
            $table->unsignedTinyInteger('pcr_incomplete_lines')->nullable()->after('pcr_ratio');
        });
    }

    public function down(): void
    {
        Schema::table('pcf_snapshots', function (Blueprint $table) {
            $table->dropColumn(['pcr_ratio', 'pcr_incomplete_lines']);
        });
    }
};
