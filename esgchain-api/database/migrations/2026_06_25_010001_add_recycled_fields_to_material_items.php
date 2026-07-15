<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_items', function (Blueprint $table) {
            $table->decimal('pir_percentage', 5, 2)->nullable()->after('pcr_percentage');
            $table->decimal('bio_based_percentage', 5, 2)->nullable()->after('pir_percentage');
            $table->enum('recyclability_rating', ['high', 'medium', 'low', 'not_rated'])->nullable()->after('bio_based_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('material_items', function (Blueprint $table) {
            $table->dropColumn(['pir_percentage', 'bio_based_percentage', 'recyclability_rating']);
        });
    }
};
