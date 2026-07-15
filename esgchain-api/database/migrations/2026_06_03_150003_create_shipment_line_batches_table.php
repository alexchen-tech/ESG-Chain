<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_line_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipment_line_id')->constrained('shipment_lines')->cascadeOnDelete();
            $table->foreignUuid('production_batch_id')->constrained('production_batches')->cascadeOnDelete();
            $table->decimal('allocated_quantity', 15, 4);
            $table->timestamps();
            $table->unique(['shipment_line_id', 'production_batch_id'], 'slb_line_batch_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_line_batches');
    }
};
