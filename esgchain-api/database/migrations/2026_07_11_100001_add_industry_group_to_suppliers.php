<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->enum('industry_group', [
                '製造業',
                '勞動密集製造',
                '農林漁業',
                '科技電子',
                '物流倉儲',
                '原物料化工',
                '服務業',
            ])->nullable()->after('industry')->comment('ESG-Chain產業分類，決定問卷加掛模組');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('industry_group');
        });
    }
};
