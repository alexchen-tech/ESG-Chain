<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 將 id 從 BIGINT 改為 CHAR(36) 以支援 UUID（HasUuids）
        DB::statement('ALTER TABLE users MODIFY id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE users DROP PRIMARY KEY');
        DB::statement('ALTER TABLE users ADD PRIMARY KEY (id)');

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('supplier_id')->nullable()->after('email')->index();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['supplier_id', 'deleted_at']);
        });
    }
};
