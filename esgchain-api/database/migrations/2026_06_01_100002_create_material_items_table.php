<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('item_code', 100)->unique()->comment('料號代碼，系統內唯一');
            $table->string('name', 200);
            $table->string('hs_code', 20)->nullable();
            $table->string('unit', 20)->nullable();
            $table->uuid('material_group_id')->nullable()->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('material_group_id')->references('id')->on('material_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_items');
    }
};
