<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_items', function (Blueprint $table) {
            // 成分分類（cotton/recycled_polyester/wool...）；化學品、服務類物料留 null 代表不計入產品成分佔比拆解
            $table->string('fiber_type', 50)->nullable()->after('recyclability_rating');
        });
    }

    public function down(): void
    {
        Schema::table('material_items', function (Blueprint $table) {
            $table->dropColumn('fiber_type');
        });
    }
};
