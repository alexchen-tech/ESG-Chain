<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_goods', function (Blueprint $table) {
            $table->dropColumn('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('trade_goods', function (Blueprint $table) {
            $table->uuid('supplier_id')->nullable()->after('id');
        });
    }
};
