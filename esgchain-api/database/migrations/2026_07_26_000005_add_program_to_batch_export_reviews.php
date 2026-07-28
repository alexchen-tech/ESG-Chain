<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 記錄這筆審查是「完整審查」還是「僅審查特定法規範疇」（見
 * MarketComplianceRule::PROGRAMS）。null 代表完整審查（涵蓋全部範疇），
 * 不影響既有沒有指定範疇的審查行為。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_export_reviews', function (Blueprint $table) {
            $table->string('program', 20)->nullable()->after('market')
                ->comment('僅審查此法規範疇；null 代表完整審查');
        });
    }

    public function down(): void
    {
        Schema::table('batch_export_reviews', function (Blueprint $table) {
            $table->dropColumn('program');
        });
    }
};
