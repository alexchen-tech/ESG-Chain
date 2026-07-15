<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignUuid('trade_good_id')->constrained('trade_goods')->cascadeOnDelete();
            $table->foreignUuid('buyer_product_id')->nullable()->constrained('buyer_products')->nullOnDelete();
            $table->decimal('total_quantity', 15, 4)->default(0);
            $table->string('unit', 20)->default('pcs');
            $table->string('hs_code_override', 10)->nullable();
            $table->decimal('weighted_pcf', 15, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_lines');
    }
};
