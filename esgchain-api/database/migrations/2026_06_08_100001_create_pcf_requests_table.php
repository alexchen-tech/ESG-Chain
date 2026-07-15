<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pcf_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->enum('status', ['pending', 'submitted', 'verified', 'overdue'])->default('pending');
            $table->uuid('saq_round_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pcf_requests');
    }
};
