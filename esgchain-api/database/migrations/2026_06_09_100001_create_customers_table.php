<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->char('country_code', 2);
            $table->string('eori_number', 20)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->enum('customer_type', ['brand', 'retailer', 'distributor', 'agent', 'oem']);
            $table->text('address')->nullable();
            $table->string('website', 255)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->uuid('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id')->index();
            $table->string('name', 100);
            $table->string('email', 150)->unique();
            $table->string('phone', 50)->nullable();
            $table->string('title', 100)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customers');
    }
};
