<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('event_type', 50)->comment('tariff_change,sanction,country_risk_update,other');
            $table->json('affected_scope')->comment('{"country_codes":["VN","CN"]}');
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamp('occurred_at');
            $table->uuid('created_by_id')->nullable();
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_events');
    }
};
