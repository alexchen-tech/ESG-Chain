<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pcf_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('celery_task_id', 100)->nullable()->index();
            $table->json('supplier_ids');
            $table->json('product_ids')->nullable();
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->tinyInteger('progress')->unsigned()->default(0);
            $table->integer('result_count')->nullable();
            $table->text('error_message')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pcf_tasks');
    }
};
