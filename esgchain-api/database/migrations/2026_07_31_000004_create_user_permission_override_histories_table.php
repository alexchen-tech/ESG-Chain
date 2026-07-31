<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 個人權限覆寫稽核紀錄（僅作稽核記錄用，不作為權限判斷的資料來源）。
 * 權限判斷的資料來源是 spatie/laravel-permission 原生的 model_has_permissions pivot 表，
 * 見 openspec/changes/crud-permission-granularity/design.md Decision 2。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_permission_override_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->string('permission');
            $table->string('action')->default('grant'); // grant | revoke
            $table->string('granted_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_override_histories');
    }
};
