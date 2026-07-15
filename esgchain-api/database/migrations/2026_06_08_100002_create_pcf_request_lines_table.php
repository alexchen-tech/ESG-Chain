<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pcf_request_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pcf_request_id')->constrained('pcf_requests')->cascadeOnDelete();
            $table->uuid('bom_line_id');
            $table->string('material_name');
            $table->string('hs_code', 20)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->enum('status', ['pending', 'submitted', 'verified'])->default('pending');
            $table->timestamps();

            $table->index(['pcf_request_id', 'status']);
            $table->unique(['pcf_request_id', 'bom_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pcf_request_lines');
    }
};
