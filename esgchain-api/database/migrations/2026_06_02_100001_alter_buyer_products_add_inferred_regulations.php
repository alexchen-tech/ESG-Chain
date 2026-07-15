<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_products', function (Blueprint $table) {
            $table->json('inferred_regulations')->nullable()->after('applicable_regulations');
        });
    }

    public function down(): void
    {
        Schema::table('buyer_products', function (Blueprint $table) {
            $table->dropColumn('inferred_regulations');
        });
    }
};
