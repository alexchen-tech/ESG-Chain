<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('buyer_product_suppliers');
    }

    public function down(): void
    {
        Schema::create('buyer_product_suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('buyer_product_id')->index();
            $table->uuid('supplier_id')->index();
            $table->timestamps();
        });
    }
};
