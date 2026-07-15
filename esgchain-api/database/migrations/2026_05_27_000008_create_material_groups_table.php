<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->json('hs_code_prefixes')->comment('HS Code 前綴陣列，用於自動推導');
            $table->json('required_doc_types')->comment('所需合規文件類型陣列');
            $table->boolean('is_system')->default(true)->comment('系統預載不可刪除');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_groups');
    }
};
