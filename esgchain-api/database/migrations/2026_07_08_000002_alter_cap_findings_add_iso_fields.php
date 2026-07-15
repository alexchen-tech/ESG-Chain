<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cap_findings', function (Blueprint $table) {
            $table->string('framework', 20)->nullable()->after('category');
            $table->string('topic_slug', 80)->nullable()->after('framework');
            $table->decimal('source_score', 5, 2)->nullable()->after('topic_slug');
            $table->decimal('threshold', 5, 2)->nullable()->after('source_score');

            // category 改為 nullable（自動產生的 finding 不填 E/S/G）
            $table->string('category')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cap_findings', function (Blueprint $table) {
            $table->dropColumn(['framework', 'topic_slug', 'source_score', 'threshold']);
            $table->string('category')->nullable(false)->change();
        });
    }
};
