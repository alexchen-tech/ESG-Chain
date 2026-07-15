<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pcf_request_lines', function (Blueprint $table) {
            $table->uuid('material_item_id')->nullable()->after('pcf_request_id');
            $table->uuid('fulfilled_emission_id')->nullable()->after('submitted_at');

            $table->foreign('material_item_id')->references('id')->on('material_items')->nullOnDelete();
            $table->foreign('fulfilled_emission_id')->references('id')->on('material_item_emissions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pcf_request_lines', function (Blueprint $table) {
            $table->dropForeign(['material_item_id']);
            $table->dropForeign(['fulfilled_emission_id']);
            $table->dropColumn(['material_item_id', 'fulfilled_emission_id']);
        });
    }
};
