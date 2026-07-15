<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE caps MODIFY COLUMN source_type ENUM('saq','compliance_doc','manual','risk_assessment') NOT NULL DEFAULT 'manual'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE caps MODIFY COLUMN source_type ENUM('saq','compliance_doc','manual') NOT NULL DEFAULT 'manual'");
    }
};
