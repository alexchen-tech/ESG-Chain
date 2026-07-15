<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE material_item_emissions MODIFY source ENUM('portal-self','buyer-input','ai-estimated','system_default') NOT NULL DEFAULT 'buyer-input'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE material_item_emissions MODIFY source ENUM('portal-self','buyer-input','ai-estimated') NOT NULL DEFAULT 'buyer-input'");
    }
};
