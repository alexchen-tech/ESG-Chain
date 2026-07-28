<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 補上一直缺失的欄位：Model::$fillable、Controller validate()、前端表單皆已在用，
    // 唯獨資料表從未真的加過這個欄位，導致供應商編輯儲存（只要送出 sasb_industry_id）必定 500。
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->uuid('sasb_industry_id')->nullable()->after('industry_group')->index();
            $table->foreign('sasb_industry_id')->references('id')->on('sasb_industries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropForeign(['sasb_industry_id']);
            $table->dropColumn('sasb_industry_id');
        });
    }
};
