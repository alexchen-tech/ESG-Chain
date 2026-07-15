<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sasb_disclosure_topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sasb_industry_id')->index();
            $table->string('topic_name');
            $table->string('topic_code', 30)->comment('SASB Accounting Metric prefix, e.g. EM-IS-110a');
            $table->enum('esg_category', ['E', 'S', 'G']);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('sasb_industry_id')->references('id')->on('sasb_industries')->cascadeOnDelete();
            $table->index(['sasb_industry_id', 'esg_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sasb_disclosure_topics');
    }
};
