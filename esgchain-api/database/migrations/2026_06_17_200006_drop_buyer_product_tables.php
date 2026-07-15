<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function fkExists(string $table, string $fkName): bool
    {
        return (bool) DB::selectOne(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $fkName]
        );
    }

    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // production_batches → buyer_product_trade_goods
        if ($this->fkExists('production_batches', 'production_batches_buyer_product_trade_good_id_foreign')) {
            Schema::table('production_batches', function (Blueprint $table) {
                $table->dropForeign('production_batches_buyer_product_trade_good_id_foreign');
            });
        }
        if ($this->columnExists('production_batches', 'buyer_product_trade_good_id')) {
            Schema::table('production_batches', function (Blueprint $table) {
                $table->dropColumn('buyer_product_trade_good_id');
            });
        }

        // chemical_compliance_alerts.buyer_product_id → buyer_products（改指向 sales_products）
        if ($this->fkExists('chemical_compliance_alerts', 'chemical_compliance_alerts_buyer_product_id_foreign')) {
            Schema::table('chemical_compliance_alerts', function (Blueprint $table) {
                $table->dropForeign('chemical_compliance_alerts_buyer_product_id_foreign');
            });
        }
        if ($this->columnExists('chemical_compliance_alerts', 'buyer_product_id')) {
            Schema::table('chemical_compliance_alerts', function (Blueprint $table) {
                $table->renameColumn('buyer_product_id', 'sales_product_id');
            });
        }
        if (!$this->fkExists('chemical_compliance_alerts', 'chemical_compliance_alerts_sales_product_id_foreign')) {
            Schema::table('chemical_compliance_alerts', function (Blueprint $table) {
                $table->foreign('sales_product_id')->references('id')->on('sales_products')->nullOnDelete();
            });
        }

        // pcf_snapshots FK 仍指向 buyer_products（migration 200003 只改欄位名未更新 FK）
        if ($this->fkExists('pcf_snapshots', 'pcf_snapshots_buyer_product_id_foreign')) {
            Schema::table('pcf_snapshots', function (Blueprint $table) {
                $table->dropForeign('pcf_snapshots_buyer_product_id_foreign');
            });
        }
        if (!$this->fkExists('pcf_snapshots', 'pcf_snapshots_sales_product_id_foreign')) {
            Schema::table('pcf_snapshots', function (Blueprint $table) {
                $table->foreign('sales_product_id')->references('id')->on('sales_products')->cascadeOnDelete();
            });
        }

        Schema::dropIfExists('buyer_product_trade_goods');
        Schema::dropIfExists('buyer_products');

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        Schema::create('buyer_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('product_code')->nullable();
            $table->text('description')->nullable();
            $table->json('applicable_regulations')->nullable();
            $table->json('inferred_regulations')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('buyer_product_trade_goods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('buyer_product_id');
            $table->uuid('trade_good_id');
            $table->string('relation_type')->default('finished_good');
            $table->uuid('bom_line_id')->nullable();
            $table->string('note')->nullable();
            $table->string('erp_product_code')->nullable();
            $table->timestamps();
        });
    }
};
