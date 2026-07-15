<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_disclosures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id');
            $table->string('field_slug', 80);
            $table->smallInteger('period_year')->unsigned();
            $table->decimal('numeric_value', 15, 4)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->text('text_value')->nullable()->comment('single_choice 選項字串');
            $table->string('evidence_url')->nullable();
            $table->enum('source', ['saq_sync', 'manual', 'erp_sync'])->default('saq_sync');
            $table->uuid('source_saq_id')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'field_slug', 'period_year'], 'uq_supplier_field_year');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->foreign('field_slug')->references('slug')->on('supplier_disclosure_fields');
            $table->foreign('source_saq_id')->references('id')->on('saqs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_disclosures');
    }
};
