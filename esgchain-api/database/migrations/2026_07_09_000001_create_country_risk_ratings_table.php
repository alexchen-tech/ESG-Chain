<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_risk_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('country_code', 2)->unique();
            $table->string('country_name', 100);
            $table->tinyInteger('labor_risk')->default(3)->comment('勞工人權風險 1–5');
            $table->tinyInteger('env_risk')->default(3)->comment('環境監管寬鬆程度 1–5');
            $table->tinyInteger('geo_risk')->default(3)->comment('地緣政治穩定性 1–5');
            $table->string('source', 50)->default('manual')->comment('manual | ITUC | WJP');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_risk_ratings');
    }
};
