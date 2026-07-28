<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 電池規格：一對一掛 SalesProduct（電池即產品本身，比照 product_packagings
 * 既有一對一模式），比照 EU 電池法規 (EU) 2023/1542 DPP 最小揭露欄位。
 * 關鍵原料回收含量、效能耐久性欄位一併併入本表——同一份人工填報表單，
 * 無版本演進/重算需求，不比照 product_circularity_snapshots 拆快照表。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_battery_specs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_product_id')->unique()->constrained('sales_products')->cascadeOnDelete();

            // 電池類別與化學系統
            $table->enum('battery_category', ['portable', 'industrial', 'ev', 'lmt'])->nullable();
            $table->string('chemistry', 60)->nullable();
            $table->decimal('rated_capacity_ah', 10, 3)->nullable();
            $table->decimal('rated_voltage_v', 8, 2)->nullable();
            $table->decimal('weight_kg', 10, 3)->nullable();

            // 關鍵原料回收含量（法規指定金屬，百分比）
            $table->decimal('lithium_recycled_content_ratio', 5, 2)->nullable();
            $table->decimal('cobalt_recycled_content_ratio', 5, 2)->nullable();
            $table->decimal('nickel_recycled_content_ratio', 5, 2)->nullable();
            $table->decimal('lead_recycled_content_ratio', 5, 2)->nullable();

            // 效能與耐久性
            $table->unsignedInteger('cycle_life')->nullable();
            $table->unsignedSmallInteger('expected_lifetime_years')->nullable();
            $table->decimal('discharge_efficiency_ratio', 5, 2)->nullable();
            $table->string('initial_capacity_soh_note', 200)->nullable();
            $table->string('operating_temp_range', 60)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_battery_specs');
    }
};
