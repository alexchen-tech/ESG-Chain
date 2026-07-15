<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // scoring_framework 已是 varchar，不需要 enum 變更
        // 僅更新 DB trigger 驗證允許的值（若存在）
        // 在應用層 validation 加入 "multi-framework" 即可
    }

    public function down(): void {}
};
