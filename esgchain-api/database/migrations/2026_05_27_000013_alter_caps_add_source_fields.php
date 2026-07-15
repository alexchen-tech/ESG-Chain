<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caps', function (Blueprint $table) {
            $table->enum('source_type', ['saq', 'compliance_doc', 'manual'])
                  ->default('manual')
                  ->after('saq_id');
            $table->uuid('source_id')->nullable()->index()->after('source_type');
        });
    }

    public function down(): void
    {
        Schema::table('caps', function (Blueprint $table) {
            $table->dropIndex(['source_id']);
            $table->dropColumn(['source_type', 'source_id']);
        });
    }
};
