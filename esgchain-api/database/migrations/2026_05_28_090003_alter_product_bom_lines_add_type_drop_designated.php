<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_bom_lines', function (Blueprint $table) {
            $table->enum('bom_line_type', ['material', 'service'])->default('material')->after('material_group_source');
        });

        Schema::table('product_bom_lines', function (Blueprint $table) {
            $table->dropForeign(['designated_supplier_id']);
            $table->dropColumn(['designated_supplier_id', 'supplier_source']);
        });
    }

    public function down(): void
    {
        Schema::table('product_bom_lines', function (Blueprint $table) {
            $table->foreignUuid('designated_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete()->after('material_group_source');
            $table->enum('supplier_source', ['erp_designated', 'manual'])->nullable()->after('designated_supplier_id');
            $table->dropColumn('bom_line_type');
        });
    }
};
