<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique()->comment('市場代碼，大寫底線格式，如 US_MARKET');
            $table->string('label', 100);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false)->comment('系統預載不可刪除');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_definitions');
    }
};
