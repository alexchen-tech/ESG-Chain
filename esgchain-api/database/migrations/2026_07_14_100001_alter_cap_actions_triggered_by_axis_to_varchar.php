<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * triggered_by_axis 從 ENUM('axis1','axis2','axis3') 改為 VARCHAR(20)。
 * 舊值（axis1/axis2/axis3）保留相容，不需回填。
 * 新值接受 dim_e1–dim_e6（六維觸發）。
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL ENUM 無法直接 ALTER，先改為 VARCHAR
        DB::statement("ALTER TABLE caps MODIFY COLUMN triggered_by_axis VARCHAR(20) NULL");
    }

    public function down(): void
    {
        // 回滾：將 dim_e* 值設為 null 後還原 ENUM
        DB::statement("UPDATE caps SET triggered_by_axis = NULL WHERE triggered_by_axis NOT IN ('axis1','axis2','axis3')");
        DB::statement("ALTER TABLE caps MODIFY COLUMN triggered_by_axis ENUM('axis1','axis2','axis3') NULL");
    }
};
