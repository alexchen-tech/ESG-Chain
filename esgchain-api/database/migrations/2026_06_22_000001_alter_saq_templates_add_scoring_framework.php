<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saq_templates', function (Blueprint $table) {
            $table->string('scoring_framework', 50)->nullable()->after('status')
                ->comment('計分框架：ESG / ISO20400 / ISO26000 / Geo-Risk / Product-Compliance / NULL(通用)');
        });
    }

    public function down(): void
    {
        Schema::table('saq_templates', function (Blueprint $table) {
            $table->dropColumn('scoring_framework');
        });
    }
};
