<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_material_origins', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('production_batch_id')->constrained('production_batches')->cascadeOnDelete();
            $table->foreignUuid('bom_line_id')->nullable()->constrained('product_bom_lines')->nullOnDelete();
            $table->string('material_name', 200);
            $table->char('origin_country', 2);
            $table->string('facility_name', 200)->nullable();
            $table->decimal('gps_lat', 9, 6)->nullable();
            $table->decimal('gps_lng', 9, 6)->nullable();
            $table->smallInteger('harvest_year')->nullable();
            $table->string('certification_ref', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_material_origins');
    }
};
