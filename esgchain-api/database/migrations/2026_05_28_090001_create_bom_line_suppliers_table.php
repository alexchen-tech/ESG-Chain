<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom_line_suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bom_line_id')->constrained('product_bom_lines')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->enum('role', ['primary', 'alternate'])->default('primary');
            $table->enum('source', ['erp_designated', 'manual'])->default('erp_designated');
            $table->tinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('bom_line_id');
            $table->index('supplier_id');
            $table->unique(['bom_line_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_line_suppliers');
    }
};
