<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->float('axis1_score')->nullable()->after('source_saq_id');
            $table->float('axis2_score')->nullable()->after('axis1_score');
            $table->float('axis3_score')->nullable()->after('axis2_score');
            $table->uuid('axis1_source_saq_id')->nullable()->after('axis3_score');
            $table->uuid('axis2_source_saq_id')->nullable()->after('axis1_source_saq_id');
        });
    }

    public function down(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->dropColumn(['axis1_score', 'axis2_score', 'axis3_score', 'axis1_source_saq_id', 'axis2_source_saq_id']);
        });
    }
};
