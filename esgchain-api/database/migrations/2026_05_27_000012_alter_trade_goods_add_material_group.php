<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_goods', function (Blueprint $table) {
            $table->uuid('material_group_id')->nullable()->after('cbam_category');
            $table->foreign('material_group_id')->references('id')->on('material_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trade_goods', function (Blueprint $table) {
            $table->dropForeign(['material_group_id']);
            $table->dropColumn('material_group_id');
        });
    }
};
