<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_bom_lines', function (Blueprint $table) {
            $table->uuid('material_item_id')->nullable()->after('buyer_product_id')->index();
            $table->foreign('material_item_id')->references('id')->on('material_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_bom_lines', function (Blueprint $table) {
            $table->dropForeign(['material_item_id']);
            $table->dropColumn('material_item_id');
        });
    }
};
