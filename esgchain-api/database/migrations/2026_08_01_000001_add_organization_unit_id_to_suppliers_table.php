<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // organization_unit_id 為 ESG-Chain 自有營運欄位（由永續團隊人工指派），不隨 ERP 同步覆蓋，
    // 供應商建立當下不強制填寫，故 nullable；刪除組織單位時解除關聯而非阻擋刪除，故 nullOnDelete。
    // 一個供應商僅歸屬單一組織單位（1 對多），非多對多。
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignUuid('organization_unit_id')
                ->nullable()
                ->after('group_id')
                ->constrained('organization_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign(['organization_unit_id']);
            $table->dropColumn('organization_unit_id');
        });
    }
};
