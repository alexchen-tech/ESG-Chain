<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_groups', function (Blueprint $table) {
            $table->json('required_doc_types')->nullable()->after('description')->comment('廠商資格文件類型陣列');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_groups', function (Blueprint $table) {
            $table->dropColumn('required_doc_types');
        });
    }
};
