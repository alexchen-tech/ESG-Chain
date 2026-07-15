<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saq_templates', function (Blueprint $table) {
            $table->uuid('sasb_industry_id')->nullable()->after('is_active');
            $table->foreign('sasb_industry_id')->references('id')->on('sasb_industries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('saq_templates', function (Blueprint $table) {
            $table->dropForeign(['sasb_industry_id']);
            $table->dropColumn('sasb_industry_id');
        });
    }
};
