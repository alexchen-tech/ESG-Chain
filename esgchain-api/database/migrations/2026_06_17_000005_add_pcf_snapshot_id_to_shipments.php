<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->uuid('pcf_snapshot_id')->nullable()->after('id');
            $table->foreign('pcf_snapshot_id')->references('id')->on('pcf_snapshots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['pcf_snapshot_id']);
            $table->dropColumn('pcf_snapshot_id');
        });
    }
};
