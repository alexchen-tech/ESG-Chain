<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 問卷模板
        Schema::create('saq_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('version')->default('1.0');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 問卷題目
        Schema::create('saq_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('template_id')->index();
            $table->string('category')->comment('E=環境, S=社會, G=治理');
            $table->text('question_text');
            $table->string('question_type')->default('single_choice')->comment('single_choice, multiple_choice, text, number, boolean');
            $table->json('options')->nullable()->comment('選項列表');
            $table->decimal('weight', 5, 2)->default(1.00);
            $table->integer('order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('saq_templates')->cascadeOnDelete();
        });

        // 問卷專案
        Schema::create('saq_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->uuid('template_id')->index();
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->date('due_date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('saq_templates');
        });

        // 問卷發送記錄
        Schema::create('saqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id')->index();
            $table->uuid('supplier_id')->index();
            $table->uuid('template_id')->index();
            $table->enum('status', ['pending', 'sent', 'in_progress', 'submitted', 'reviewing', 'approved', 'rejected'])->default('pending');
            $table->decimal('score', 5, 2)->nullable()->comment('SAQ 總分（FastAPI 計算後回填）');
            $table->string('grade')->nullable()->comment('SGS 五級：A/B/C/D/E');
            $table->string('scoring_job_id')->nullable()->comment('Celery job ID');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('saq_projects')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('saq_templates');
        });

        // 問卷回覆
        Schema::create('saq_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('saq_id')->index();
            $table->uuid('question_id')->index();
            $table->text('answer')->nullable();
            $table->json('answer_options')->nullable();
            $table->text('evidence_note')->nullable();
            $table->timestamps();

            $table->foreign('saq_id')->references('id')->on('saqs')->cascadeOnDelete();
            $table->foreign('question_id')->references('id')->on('saq_questions');
            $table->unique(['saq_id', 'question_id']);
        });

        // 審查記錄
        Schema::create('saq_review_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('saq_id')->index();
            $table->string('action')->comment('send, submit, approve, reject, request_revision');
            $table->uuid('acted_by')->nullable()->index();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('saq_id')->references('id')->on('saqs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saq_review_histories');
        Schema::dropIfExists('saq_responses');
        Schema::dropIfExists('saqs');
        Schema::dropIfExists('saq_projects');
        Schema::dropIfExists('saq_questions');
        Schema::dropIfExists('saq_templates');
    }
};
