<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE risk_assessments ADD COLUMN source_saq_id CHAR(36) NULL AFTER notes');
        DB::statement('ALTER TABLE risk_assessments ADD CONSTRAINT fk_risk_assessments_source_saq FOREIGN KEY (source_saq_id) REFERENCES saqs(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE risk_assessments DROP FOREIGN KEY fk_risk_assessments_source_saq');
        DB::statement('ALTER TABLE risk_assessments DROP COLUMN source_saq_id');
    }
};
