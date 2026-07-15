<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saq_projects', function (Blueprint $table) {
            $table->string('domain', 30)->nullable()->after('description')
                  ->comment('ESG | ISO20400 | Geo-Risk | Product-Compliance；NULL 表示通用型，計分時不過濾 domain');
        });
    }

    public function down(): void
    {
        Schema::table('saq_projects', function (Blueprint $table) {
            $table->dropColumn('domain');
        });
    }
};
