<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_bom_lines', function (Blueprint $table) {
            $table->enum('erp_sync_source', ['csv', 'webhook', 'scheduled', 'manual'])->nullable()->after('notes');
            $table->timestamp('erp_synced_at')->nullable()->after('erp_sync_source');
        });
    }

    public function down(): void
    {
        Schema::table('product_bom_lines', function (Blueprint $table) {
            $table->dropColumn(['erp_sync_source', 'erp_synced_at']);
        });
    }
};
