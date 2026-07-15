<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('shipment_no', 100)->unique();
            $table->string('target_market', 10);
            $table->date('export_date')->nullable();
            $table->enum('eudr_dds_status', ['not_required', 'draft', 'submitted', 'approved'])->default('draft');
            $table->string('eudr_dds_ref', 200)->nullable();
            $table->timestamp('eudr_submitted_at')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
