<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL enum 修改需直接用 DB statement
        DB::statement("ALTER TABLE pcf_requests MODIFY COLUMN status ENUM('pending','partial','submitted','verified','overdue') NOT NULL DEFAULT 'pending'");

        Schema::table('pcf_requests', function (Blueprint $table) {
            $table->enum('trigger_source', ['system_bom_import', 'system_supplier_change', 'buyer_manual'])
                  ->nullable()
                  ->after('status');
            $table->text('notes')->nullable()->after('trigger_source');
        });
    }

    public function down(): void
    {
        Schema::table('pcf_requests', function (Blueprint $table) {
            $table->dropColumn(['trigger_source', 'notes']);
        });

        DB::statement("ALTER TABLE pcf_requests MODIFY COLUMN status ENUM('pending','submitted','verified','overdue') NOT NULL DEFAULT 'pending'");
    }
};
