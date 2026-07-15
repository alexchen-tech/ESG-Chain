<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_products', function (Blueprint $table) {
            // 生產批號屬批次實體（production_batches，一對多），不應反正規化存於產品主檔
            $table->dropColumn('production_batch_no');
        });
    }

    public function down(): void
    {
        Schema::table('sales_products', function (Blueprint $table) {
            $table->string('production_batch_no', 100)->nullable()->after('model_no');
        });
    }
};
