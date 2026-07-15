<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_goods', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->uuid('supplier_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trade_goods', function (Blueprint $table) {
            $table->uuid('supplier_id')->nullable(false)->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });
    }
};
