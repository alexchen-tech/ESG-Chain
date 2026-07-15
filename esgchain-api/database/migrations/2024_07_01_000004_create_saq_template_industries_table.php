<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saq_template_industries', function (Blueprint $table) {
            $table->uuid('template_id');
            $table->uuid('industry_id');
            $table->primary(['template_id', 'industry_id']);

            $table->foreign('template_id')->references('id')->on('saq_templates')->cascadeOnDelete();
            $table->foreign('industry_id')->references('id')->on('sasb_industries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saq_template_industries');
    }
};
