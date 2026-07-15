<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saq_templates', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published', 'archived'])->default('published')->after('version');
            $table->uuid('draft_of')->nullable()->after('status');
        });

        // 現有資料：有 archived_at 的設為 archived，其餘設為 published
        DB::statement("UPDATE saq_templates SET status = 'archived' WHERE archived_at IS NOT NULL");
    }

    public function down(): void
    {
        Schema::table('saq_templates', function (Blueprint $table) {
            $table->dropColumn(['status', 'draft_of']);
        });
    }
};
