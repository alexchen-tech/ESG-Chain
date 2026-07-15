<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_bom_lines', function (Blueprint $table) {
            $table->enum('linkage_status', ['linked', 'unlinked'])->default('unlinked')->after('material_group_source');
        });

        // 回填既有資料
        DB::statement("
            UPDATE product_bom_lines
            SET linkage_status = IF(material_item_id IS NOT NULL, 'linked', 'unlinked')
        ");
    }

    public function down(): void
    {
        Schema::table('product_bom_lines', function (Blueprint $table) {
            $table->dropColumn('linkage_status');
        });
    }
};
