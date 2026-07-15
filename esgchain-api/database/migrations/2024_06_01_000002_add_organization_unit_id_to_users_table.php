<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('organization_unit_id')->nullable()->after('supplier_id');
            $table->foreign('organization_unit_id')->references('id')->on('organization_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_unit_id']);
            $table->dropColumn('organization_unit_id');
        });
    }
};
