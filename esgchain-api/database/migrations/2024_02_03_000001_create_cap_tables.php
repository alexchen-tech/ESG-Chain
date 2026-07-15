<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id')->index();
            $table->uuid('saq_id')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'in_progress', 'completed', 'overdue', 'closed'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->date('due_date')->nullable();
            $table->uuid('assigned_to')->nullable()->index();
            $table->uuid('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->foreign('saq_id')->references('id')->on('saqs')->nullOnDelete();
        });

        Schema::create('cap_findings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cap_id')->index();
            $table->string('category')->comment('E/S/G');
            $table->text('finding');
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->date('target_date')->nullable();
            $table->timestamps();

            $table->foreign('cap_id')->references('id')->on('caps')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cap_findings');
        Schema::dropIfExists('caps');
    }
};
