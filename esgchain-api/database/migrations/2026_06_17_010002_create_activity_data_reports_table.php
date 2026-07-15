<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_data_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_facility_id')->constrained('supplier_facilities')->cascadeOnDelete();
            $table->string('report_period', 10);   // 格式: 2024-Q1
            $table->decimal('electricity_kwh', 14, 2)->nullable();
            $table->decimal('natural_gas_gj', 14, 2)->nullable();
            $table->decimal('fuel_oil_l', 14, 2)->nullable();
            $table->decimal('heat_gj', 14, 2)->nullable();
            $table->decimal('water_m3', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'submitted', 'verified'])->default('draft');
            $table->json('push_log')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_facility_id', 'report_period']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_data_reports');
    }
};
