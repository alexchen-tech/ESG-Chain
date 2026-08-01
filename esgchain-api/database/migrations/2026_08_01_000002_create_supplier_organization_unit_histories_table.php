<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 比照 supplier_status_histories 的稽核歷程模式，記錄供應商組織單位指派/變更/清空。
    public function up(): void
    {
        Schema::create('supplier_organization_unit_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->uuid('from_organization_unit_id')->nullable();
            $table->uuid('to_organization_unit_id')->nullable();
            $table->uuid('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // 表名+欄位名超過 MySQL 64 字元識別碼上限，手動指定較短的 FK constraint 名稱
            $table->foreign('from_organization_unit_id', 'souh_from_ou_fk')
                ->references('id')->on('organization_units')->nullOnDelete();
            $table->foreign('to_organization_unit_id', 'souh_to_ou_fk')
                ->references('id')->on('organization_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_organization_unit_histories');
    }
};
