<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // B2a — saq_templates 補 created_by_id
        Schema::table('saq_templates', function (Blueprint $table) {
            $table->uuid('created_by_id')->nullable()->after('is_active');
        });

        // B2c — sasb_industries（新建）
        Schema::create('sasb_industries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sector');
            $table->string('industry');
            $table->string('code', 20)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sasb_industries');
        Schema::table('saq_templates', function (Blueprint $table) {
            $table->dropColumn('created_by_id');
        });
    }
};
