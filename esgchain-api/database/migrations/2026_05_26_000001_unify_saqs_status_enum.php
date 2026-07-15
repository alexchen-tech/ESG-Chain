<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE saqs MODIFY status ENUM('sent','in_progress','submitted','under_review','review_returned','completed','reviewed') NOT NULL DEFAULT 'sent'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE saqs MODIFY status ENUM('not_started','in_progress','submitted','under_review','review_returned','completed','reviewed') NOT NULL DEFAULT 'not_started'");
    }
};
