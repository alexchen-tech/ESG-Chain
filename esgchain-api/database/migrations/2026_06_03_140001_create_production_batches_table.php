<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('erp_batch_no', 100)->unique();
            $table->string('erp_order_no', 100)->nullable();
            $table->foreignUuid('buyer_product_trade_good_id')->nullable()->constrained('buyer_product_trade_goods')->nullOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('production_date')->nullable();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->string('unit', 20)->default('pcs');
            $table->decimal('lot_pcf', 15, 4)->nullable();
            $table->enum('lot_pcf_source', ['calculated', 'reported', 'estimated'])->nullable();
            $table->enum('source', ['webhook', 'csv', 'manual'])->default('manual');
            $table->timestamp('erp_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_batches');
    }
};
