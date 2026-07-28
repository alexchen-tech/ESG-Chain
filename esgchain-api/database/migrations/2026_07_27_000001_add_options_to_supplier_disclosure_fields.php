<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_disclosure_fields', function (Blueprint $table) {
            // single_choice 欄位的可選項清單（[{value,label}, ...]），
            // numeric/boolean 欄位不使用，保持 null
            $table->json('options')->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_disclosure_fields', function (Blueprint $table) {
            $table->dropColumn('options');
        });
    }
};
