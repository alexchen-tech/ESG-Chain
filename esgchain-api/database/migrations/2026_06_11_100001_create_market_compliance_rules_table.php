<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_compliance_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('market', 10)->comment('EU/US/NA/APAC/GB/JP');
            $table->string('doc_type', 50)->comment('EUDR_DDS/CBAM_REPORT/UFLPA_DECLARATION/...');
            $table->boolean('is_mandatory')->default(true);
            $table->date('effective_from');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['market', 'doc_type']);
            $table->index(['market', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_compliance_rules');
    }
};
