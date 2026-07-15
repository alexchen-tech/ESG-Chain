<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pcf_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('buyer_product_id')->index();
            $table->decimal('total_pcf', 12, 6)->nullable();
            $table->string('functional_unit', 50)->default('件');
            $table->boolean('iso14067_ready')->default(false);
            $table->timestamp('snapshot_at')->useCurrent();
            $table->json('lines')->nullable()->comment('BomLine 明細快照，含 ISO 14067 DQI 欄位');
            $table->timestamps();

            $table->foreign('buyer_product_id')->references('id')->on('buyer_products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pcf_snapshots');
    }
};
