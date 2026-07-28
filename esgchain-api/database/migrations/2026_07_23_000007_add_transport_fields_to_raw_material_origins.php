<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_material_origins', function (Blueprint $table) {
            $table->enum('transport_mode', ['sea', 'air', 'road', 'rail', 'multimodal', 'unknown'])
                ->nullable()->after('certification_ref');
            $table->decimal('transport_distance_km', 10, 2)->nullable()->after('transport_mode');
        });
    }

    public function down(): void
    {
        Schema::table('raw_material_origins', function (Blueprint $table) {
            $table->dropColumn(['transport_mode', 'transport_distance_km']);
        });
    }
};
