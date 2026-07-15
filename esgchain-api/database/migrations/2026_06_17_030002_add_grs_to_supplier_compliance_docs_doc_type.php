<?php

use Illuminate\Database\Migrations\Migration;

// doc_type 欄位為 varchar(50)，無需修改 enum 定義即可接受 GRS 值
return new class extends Migration
{
    public function up(): void
    {
        // No-op: varchar(50) already accepts 'GRS' as a value
    }

    public function down(): void
    {
        // No-op
    }
};
