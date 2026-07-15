<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_compliance_rules', function (Blueprint $table) {
            // material=依產品物料群組的文件需求觸發；product=該市場一律適用（產品層規則，如 DPP/CPSIA）
            $table->enum('scope', ['material', 'product'])->default('material')->after('doc_type')
                ->comment('規則適用層級：material 依物料群組觸發 / product 市場內一律適用');
        });
    }

    public function down(): void
    {
        Schema::table('market_compliance_rules', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
