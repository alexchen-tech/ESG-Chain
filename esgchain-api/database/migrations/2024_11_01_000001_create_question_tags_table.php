<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('l1_domain', 40);
            $table->string('l2_pillar', 40);
            $table->string('l3_topic', 60);
            $table->string('slug', 80)->unique()->comment('建立後不可修改，為 esgchain-ai scoring_router 識別鍵');
            $table->string('label_zh', 100)->nullable();
            $table->string('label_en', 100)->nullable();
            $table->string('scoring_engine_key', 60)->nullable();
            $table->timestamp('deprecated_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('l1_domain');
            $table->index(['l1_domain', 'l2_pillar']);
            $table->index('deprecated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_tags');
    }
};
