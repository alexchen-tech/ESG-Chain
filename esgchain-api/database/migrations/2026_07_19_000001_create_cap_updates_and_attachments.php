<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CAP 稽核歷程：每次狀態變更 / 備註都落地成一筆紀錄，取代原本前端送出即消失的「備註」欄位
        Schema::create('cap_updates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cap_id')->index();
            $table->enum('status', ['open', 'in_progress', 'completed', 'overdue', 'closed'])
                ->comment('本次更新後的狀態');
            $table->text('notes')->nullable();
            $table->uuid('changed_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('cap_id')->references('id')->on('caps')->cascadeOnDelete();
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
        });

        // 佐證文件：掛在 CAP 底下，可選擇性關聯到某一次歷程更新
        Schema::create('cap_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cap_id')->index();
            $table->uuid('cap_update_id')->nullable()->index();
            $table->string('file_path');
            $table->string('file_name');
            $table->uuid('uploaded_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('cap_id')->references('id')->on('caps')->cascadeOnDelete();
            $table->foreign('cap_update_id')->references('id')->on('cap_updates')->nullOnDelete();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cap_attachments');
        Schema::dropIfExists('cap_updates');
    }
};
