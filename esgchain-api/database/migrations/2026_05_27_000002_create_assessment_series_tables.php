<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 100)->unique();
            $table->string('name', 200);
            $table->string('domain', 50)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('assessment_series_weights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('series_id');
            $table->uuid('source_question_id');
            $table->decimal('weight', 5, 4);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['series_id', 'source_question_id'], 'uq_series_question');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_series_weights');
        Schema::dropIfExists('assessment_series');
    }
};
