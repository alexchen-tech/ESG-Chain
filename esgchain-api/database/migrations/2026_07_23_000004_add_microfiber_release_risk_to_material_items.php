<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_items', function (Blueprint $table) {
            $table->enum('microfiber_release_risk', ['low', 'medium', 'high', 'not_rated'])
                ->default('not_rated')
                ->after('recyclability_rating');
        });
    }

    public function down(): void
    {
        Schema::table('material_items', function (Blueprint $table) {
            $table->dropColumn('microfiber_release_risk');
        });
    }
};
