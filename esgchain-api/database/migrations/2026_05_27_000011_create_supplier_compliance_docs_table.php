<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_compliance_docs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id')->index();
            $table->uuid('trade_good_id')->nullable()->index();
            $table->string('doc_type', 50)->comment('UFLPA_DECLARATION|EUDR_DDS|CMRT|SDS|CE_DOC|ORIGIN_CERT|OTHER');
            $table->string('file_path', 500);
            $table->string('file_name', 255)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->foreign('trade_good_id')->references('id')->on('trade_goods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_compliance_docs');
    }
};
