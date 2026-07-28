<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // append-only，比照 pcf_snapshots：每次重新計算都是新增一筆，不更新舊快照
    public function up(): void
    {
        Schema::create('product_circularity_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sales_product_id')->index();
            $table->decimal('total_weight_kg', 12, 4)->nullable();
            $table->decimal('recycled_content_ratio', 6, 4)->nullable()->comment('PCR+PIR 加權後的整體回收材料比');
            $table->decimal('pcr_ratio', 6, 4)->nullable();
            $table->decimal('pir_ratio', 6, 4)->nullable();
            $table->decimal('bio_based_ratio', 6, 4)->nullable();
            $table->json('composition_breakdown')->nullable()->comment('[{fiber_type, weight_kg, percentage}]');
            $table->json('recyclability_summary')->nullable()->comment('{high/medium/low/not_rated: percentage}');
            $table->unsignedInteger('incomplete_lines_count')->default(0);
            $table->boolean('data_ready')->default(false);
            $table->timestamp('calculated_at')->useCurrent();
            $table->timestamps();

            $table->foreign('sales_product_id')->references('id')->on('sales_products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_circularity_snapshots');
    }
};
