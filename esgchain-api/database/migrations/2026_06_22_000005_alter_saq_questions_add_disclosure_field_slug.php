<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saq_questions', function (Blueprint $table) {
            $table->string('disclosure_field_slug', 80)->nullable()->after('compliance_domains')
                ->comment('對應的 supplier_disclosure_fields.slug，null 表示此題不映射至 disclosure');
        });
    }

    public function down(): void
    {
        Schema::table('saq_questions', function (Blueprint $table) {
            $table->dropColumn('disclosure_field_slug');
        });
    }
};
