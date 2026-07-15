<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_disclosure_fields', function (Blueprint $table) {
            $table->string('slug', 80)->primary()->comment('語意識別碼，格式 <domain>.<metric>');
            $table->string('label', 120)->comment('繁體中文顯示名稱');
            $table->enum('data_type', ['numeric', 'boolean', 'single_choice'])->comment('資料型態');
            $table->string('unit', 40)->nullable()->comment('單位（numeric 才有）');
            $table->enum('period_type', ['annual', 'point_in_time'])->default('annual');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_disclosure_fields');
    }
};
