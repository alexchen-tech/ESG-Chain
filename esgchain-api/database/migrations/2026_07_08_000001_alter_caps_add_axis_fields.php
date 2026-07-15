<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caps', function (Blueprint $table) {
            $table->enum('triggered_by_axis', ['axis1', 'axis2', 'axis3'])->nullable()->after('source_id');
            $table->boolean('auto_generated')->default(false)->after('triggered_by_axis');
        });
    }

    public function down(): void
    {
        Schema::table('caps', function (Blueprint $table) {
            $table->dropColumn(['triggered_by_axis', 'auto_generated']);
        });
    }
};
