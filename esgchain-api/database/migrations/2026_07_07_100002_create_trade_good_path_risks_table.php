<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_good_path_risks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trade_good_id');
            $table->string('market', 20);
            $table->float('path_risk_score')->nullable();
            $table->string('risk_level', 20)->nullable();
            $table->float('amplifier')->nullable();
            $table->float('chain_risk')->nullable();
            $table->boolean('has_data_gap')->default(false);
            $table->json('contributors')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['trade_good_id', 'market']);
            $table->foreign('trade_good_id')->references('id')->on('sales_products')->onDelete('cascade');
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_good_path_risks');
    }
};
