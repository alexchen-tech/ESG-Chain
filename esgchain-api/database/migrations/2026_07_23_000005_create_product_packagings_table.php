<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_packagings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_product_id')->unique()->constrained('sales_products')->cascadeOnDelete();
            $table->decimal('recycled_content_ratio', 5, 2)->nullable();
            $table->boolean('recyclable')->nullable();
            $table->boolean('reusable')->nullable();
            $table->string('material_description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_packagings');
    }
};
