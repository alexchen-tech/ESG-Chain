<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 法規範疇（program）：讓「執行審查」可以選定市場後，進一步篩選只跑哪個法規範疇的
 * 檢查項目（如 DPP／CBAM／EUDR／UFLPA），不用每次都跑完整套檢查。
 * 依既有 doc_type 語意回填既有資料，其餘（各市場通用文件如 SDS/ORIGIN_CERT/CPSIA
 * 等）歸類為 general，執行審查時不指定 program 即涵蓋全部範疇（向下相容既有行為）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_compliance_rules', function (Blueprint $table) {
            $table->enum('program', ['general', 'dpp', 'cbam', 'eudr', 'uflpa'])->default('general')->after('doc_type')
                ->comment('法規範疇，供執行審查時篩選只跑哪個範疇的檢查項目');
        });

        DB::table('market_compliance_rules')->where('doc_type', 'EUDR_DDS')->update(['program' => 'eudr']);
        DB::table('market_compliance_rules')->where('doc_type', 'CBAM_REPORT')->update(['program' => 'cbam']);
        DB::table('market_compliance_rules')->where('doc_type', 'UFLPA_DECLARATION')->update(['program' => 'uflpa']);
        DB::table('market_compliance_rules')->where('doc_type', 'DPP_DECLARATION')->update(['program' => 'dpp']);
    }

    public function down(): void
    {
        Schema::table('market_compliance_rules', function (Blueprint $table) {
            $table->dropColumn('program');
        });
    }
};
