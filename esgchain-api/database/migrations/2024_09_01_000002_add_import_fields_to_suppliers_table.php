<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('vat_number', 50)->nullable()->after('code');
            $table->json('erp_vendor_codes')->nullable()->after('vat_number');
            $table->decimal('spend_amount', 15, 2)->nullable()->after('risk_score');
            $table->json('tags')->nullable()->after('spend_amount');
            $table->boolean('profile_completed')->default(false)->after('tags');

            $table->index('vat_number');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['vat_number']);
            $table->dropColumn(['vat_number', 'erp_vendor_codes', 'spend_amount', 'tags', 'profile_completed']);
        });
    }
};
