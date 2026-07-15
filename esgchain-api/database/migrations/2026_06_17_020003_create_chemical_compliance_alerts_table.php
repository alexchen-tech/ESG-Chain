<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chemical_compliance_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('buyer_product_id')->nullable()->constrained('buyer_products')->nullOnDelete();
            $table->foreignUuid('material_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('material_item_chemical_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('chemical_id')->nullable()->constrained('chemicals')->nullOnDelete();
            $table->string('regulated_list', 50);
            $table->enum('alert_level', ['info', 'warning', 'critical'])->default('warning');
            $table->enum('status', ['open', 'acknowledged', 'resolved'])->default('open');
            $table->text('notes')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledged_by')->nullable();
            $table->timestamps();

            $table->index(['material_item_id', 'status']);
            $table->index(['buyer_product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chemical_compliance_alerts');
    }
};
