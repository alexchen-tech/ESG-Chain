<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('framework_default_weights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scoring_framework', 30);
            $table->string('pillar_slug', 60);
            $table->decimal('weight', 5, 4);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['scoring_framework', 'pillar_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('framework_default_weights');
    }
};
