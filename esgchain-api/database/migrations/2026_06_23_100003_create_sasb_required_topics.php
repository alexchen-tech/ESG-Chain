<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sasb_required_topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sasb_industry_code', 20);
            $table->string('tag_slug', 80);
            $table->text('rationale')->nullable();
            $table->timestamps();

            $table->unique(['sasb_industry_code', 'tag_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sasb_required_topics');
    }
};
