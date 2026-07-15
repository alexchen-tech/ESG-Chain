<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_goods', function (Blueprint $table) {
            $table->string('product_code', 100)->nullable()->after('name');
            $table->boolean('is_eudr_applicable')->default(false)->after('is_cbam_applicable');
        });
    }

    public function down(): void
    {
        Schema::table('trade_goods', function (Blueprint $table) {
            $table->dropColumn(['product_code', 'is_eudr_applicable']);
        });
    }
};
