<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_items', function (Blueprint $table) {
            $table->decimal('net_weight', 10, 4)->nullable()->after('hs_code');
            $table->decimal('pcr_percentage', 5, 2)->nullable()->after('net_weight');
        });
    }

    public function down(): void
    {
        Schema::table('material_items', function (Blueprint $table) {
            $table->dropColumn(['net_weight', 'pcr_percentage']);
        });
    }
};
