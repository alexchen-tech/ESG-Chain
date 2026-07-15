<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_groups', function (Blueprint $table) {
            $table->enum('group_type', ['material', 'service'])->default('material')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('material_groups', function (Blueprint $table) {
            $table->dropColumn('group_type');
        });
    }
};
