<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 批號當下實際供應這批原料的供應商，由核可清單（material_item_suppliers /
 * 既有 bom_line_suppliers）中選定，屬批號層級事實，非產品固定屬性。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_material_origins', function (Blueprint $table) {
            $table->foreignUuid('supplier_id')->nullable()->after('bom_line_id')
                ->constrained('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('raw_material_origins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
