<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('country_risk_ratings', function (Blueprint $table) {
            $table->json('sub_scores')->nullable()->after('geo_risk')
                ->comment('地緣風險細分支柱：{political, environmental, social, regulatory} 各 1–5');
        });
    }

    public function down(): void
    {
        Schema::table('country_risk_ratings', function (Blueprint $table) {
            $table->dropColumn('sub_scores');
        });
    }
};
