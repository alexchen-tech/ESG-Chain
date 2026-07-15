<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->enum('type', ['headquarters', 'subsidiary', 'business_unit', 'department', 'branch']);
            $table->uuid('parent_id')->nullable();
            $table->char('country_code', 2)->default('TW');
            $table->tinyInteger('depth')->default(1);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('organization_units')->nullOnDelete();
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_units');
    }
};
