<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_bom_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('buyer_product_id')->constrained('buyer_products')->cascadeOnDelete();
            $table->string('erp_line_id')->nullable();
            $table->string('material_name');
            $table->string('hs_code', 10)->nullable();
            $table->foreignUuid('material_group_id')->nullable()->constrained('material_groups')->nullOnDelete();
            $table->enum('material_group_source', ['erp_imported', 'hs_inferred', 'manual'])->nullable();
            $table->foreignUuid('designated_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->enum('supplier_source', ['erp_designated', 'manual'])->nullable();
            $table->decimal('quantity', 15, 4)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->char('currency', 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['buyer_product_id', 'erp_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bom_lines');
    }
};
