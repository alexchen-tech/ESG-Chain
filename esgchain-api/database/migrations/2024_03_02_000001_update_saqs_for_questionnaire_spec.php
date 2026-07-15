<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 新增新欄位
        Schema::table('saqs', function (Blueprint $table) {
            $table->timestamp('review_started_at')->nullable()->after('submitted_at');
            $table->uuid('reviewed_by_id')->nullable()->after('review_started_at');
        });

        // 2. 遷移狀態值
        $statusMap = [
            'pending'     => 'not_started',
            'sent'        => 'not_started',
            'in_progress' => 'in_progress',
            'submitted'   => 'submitted',
            'reviewing'   => 'under_review',
            'approved'    => 'completed',
            'rejected'    => 'review_returned',
        ];

        foreach ($statusMap as $old => $new) {
            DB::statement("UPDATE saqs SET status = ? WHERE status = ?", [$new, $old]);
        }

        // 3. 修改 status ENUM
        DB::statement("ALTER TABLE saqs MODIFY COLUMN status ENUM(
            'not_started','in_progress','submitted',
            'under_review','review_returned','completed','reviewed'
        ) NOT NULL DEFAULT 'not_started'");
    }

    public function down(): void
    {
        Schema::table('saqs', function (Blueprint $table) {
            $table->dropColumn(['review_started_at', 'reviewed_by_id']);
        });
    }
};
