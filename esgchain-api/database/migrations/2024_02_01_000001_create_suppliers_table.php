<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('group_id')->nullable()->index();
            $table->string('name');
            $table->string('code')->unique()->nullable();
            $table->string('country', 2)->nullable();
            $table->string('industry')->nullable();
            $table->tinyInteger('tier')->default(1)->comment('1=Tier1, 2=Tier2, 3=Tier3');
            $table->enum('status', ['potential','invited','reviewing','certified','suspended','terminated'])->default('potential');
            $table->decimal('risk_score', 5, 2)->nullable();
            $table->text('address')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('group_id')->references('id')->on('supplier_groups')->nullOnDelete();
        });

        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id')->index();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('title')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });

        Schema::create('supplier_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('supplier_id')->index();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->uuid('changed_by')->nullable()->index();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_status_histories');
        Schema::dropIfExists('supplier_contacts');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('supplier_groups');
    }
};
