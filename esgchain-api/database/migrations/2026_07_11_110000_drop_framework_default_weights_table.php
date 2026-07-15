<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('framework_default_weights');
    }

    public function down(): void
    {
        Schema::create('framework_default_weights', function ($table) {
            $table->id();
            $table->string('scoring_framework', 50);
            $table->string('pillar_slug', 80);
            $table->decimal('weight', 5, 4)->default(0);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['scoring_framework', 'pillar_slug']);
        });
    }
};
