<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_item_chemicals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('material_item_id')->constrained()->cascadeOnDelete();
            $table->string('cas_no', 15);
            $table->decimal('weight_percentage', 5, 2)->nullable();
            $table->decimal('reporting_threshold', 5, 2)->nullable();
            $table->enum('source', ['portal_supplier', 'buyer_input', 'ai_estimated'])->default('buyer_input');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['material_item_id', 'cas_no']);
            $table->index('cas_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_item_chemicals');
    }
};
