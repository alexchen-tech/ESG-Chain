<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saq_questions', function (Blueprint $table) {
            $table->json('compliance_domains')->nullable()->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('saq_questions', function (Blueprint $table) {
            $table->dropColumn('compliance_domains');
        });
    }
};
