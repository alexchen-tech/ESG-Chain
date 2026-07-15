<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_product_suppliers', function (Blueprint $table) {
            $table->dropForeign(['material_group_id']);
            $table->dropIndex('bps_composite_idx');
            $table->dropColumn('material_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('buyer_product_suppliers', function (Blueprint $table) {
            $table->uuid('material_group_id')->nullable()->index()->after('supplier_id');
            $table->foreign('material_group_id')->references('id')->on('material_groups')->nullOnDelete();
            $table->index(['buyer_product_id', 'supplier_id', 'material_group_id'], 'bps_composite_idx');
        });
    }
};
