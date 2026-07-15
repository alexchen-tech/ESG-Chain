<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_facilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('name');
            $table->string('country', 2)->nullable();
            $table->text('address')->nullable();
            $table->enum('facility_type', ['manufacturing', 'warehouse', 'office', 'other'])->default('manufacturing');
            $table->json('energy_types')->nullable();
            $table->text('main_products')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['supplier_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_facilities');
    }
};
