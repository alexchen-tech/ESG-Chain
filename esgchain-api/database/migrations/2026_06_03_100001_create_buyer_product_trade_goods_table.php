<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_product_trade_goods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('buyer_product_id')->constrained('buyer_products')->cascadeOnDelete();
            $table->foreignUuid('trade_good_id')->constrained('trade_goods')->cascadeOnDelete();
            $table->enum('relation_type', ['finished_good', 'component', 'equivalent'])->default('finished_good');
            $table->foreignUuid('bom_line_id')->nullable()->constrained('product_bom_lines')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['buyer_product_id', 'trade_good_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_product_trade_goods');
    }
};
