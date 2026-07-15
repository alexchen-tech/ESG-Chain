<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_item_emissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('material_item_id')->index();
            $table->uuid('supplier_id')->nullable()->index();
            $table->decimal('emissions_value', 12, 6);
            $table->enum('source', ['portal-self', 'buyer-input', 'ai-estimated'])->default('portal-self');
            $table->string('calculation_method', 100)->nullable();
            $table->string('reported_period', 20)->nullable()->comment('e.g. 2025-Q4');
            $table->boolean('is_estimated')->default(false);
            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason', 500)->nullable();
            $table->timestamp('reported_at')->useCurrent();
            $table->timestamps();

            $table->foreign('material_item_id')->references('id')->on('material_items')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_item_emissions');
    }
};
