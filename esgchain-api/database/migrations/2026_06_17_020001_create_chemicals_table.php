<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chemicals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('cas_no', 15)->unique();
            $table->string('substance_name');
            $table->text('iupac_name')->nullable();
            $table->json('regulated_lists')->nullable();
            $table->text('restriction_notes')->nullable();
            $table->date('svhc_date')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chemicals');
    }
};
