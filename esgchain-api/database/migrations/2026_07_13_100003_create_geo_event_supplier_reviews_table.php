<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_event_supplier_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('geo_event_id');
            $table->uuid('supplier_id');
            $table->enum('status', ['pending', 'recalculating', 'done', 'failed'])->default('pending');
            $table->decimal('pre_e4_score', 5, 2)->nullable();
            $table->decimal('post_e4_score', 5, 2)->nullable();
            $table->uuid('risk_assessment_id')->nullable();
            $table->timestamp('recalculation_started_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('geo_event_id')->references('id')->on('geo_events')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->index(['geo_event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_event_supplier_reviews');
    }
};
