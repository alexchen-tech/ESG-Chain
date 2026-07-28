<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 批次×製程類型→實際供應商/廠區的選定紀錄。製程類型清單由該批次 BOM 涉及的
 * 核可供應商 SupplierFacility.facility_type 聯集決定（不新增製程需求欄位），
 * 見 openspec/changes/batch-process-facility-selection/design.md。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_process_facilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('production_batch_id')->constrained('production_batches')->cascadeOnDelete();
            $table->string('process_type', 50);
            $table->foreignUuid('supplier_id')->constrained('suppliers');
            $table->foreignUuid('supplier_facility_id')->nullable()->constrained('supplier_facilities')->nullOnDelete();
            $table->timestamps();

            $table->unique(['production_batch_id', 'process_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_process_facilities');
    }
};
