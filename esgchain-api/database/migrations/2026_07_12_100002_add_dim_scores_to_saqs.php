<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saqs', function (Blueprint $table) {
            $table->decimal('dim_e1', 6, 2)->nullable()->after('score_g');
            $table->decimal('dim_e2', 6, 2)->nullable()->after('dim_e1');
            $table->decimal('dim_e3', 6, 2)->nullable()->after('dim_e2');
            $table->decimal('dim_e4', 6, 2)->nullable()->after('dim_e3');
            $table->decimal('dim_e5', 6, 2)->nullable()->after('dim_e4');
            $table->decimal('dim_e6', 6, 2)->nullable()->after('dim_e5');
        });
    }

    public function down(): void
    {
        Schema::table('saqs', function (Blueprint $table) {
            $table->dropColumn(['dim_e1','dim_e2','dim_e3','dim_e4','dim_e5','dim_e6']);
        });
    }
};
