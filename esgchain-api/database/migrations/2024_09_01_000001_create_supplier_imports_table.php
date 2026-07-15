<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('batch_id', 36)->index();
            $table->string('vendor_code', 50)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->string('vendor_name', 255);
            $table->decimal('spend_amount', 15, 2)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('material_group', 100)->nullable();
            $table->string('primary_email', 255)->nullable();
            $table->enum('cleanse_status', ['staged', 'cleansed', 'rejected', 'approved', 'exempt'])
                  ->default('staged');
            $table->json('failure_codes')->nullable();
            $table->text('notes')->nullable();
            $table->json('erp_vendor_codes')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'cleanse_status']);
            $table->index('vat_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_imports');
    }
};
