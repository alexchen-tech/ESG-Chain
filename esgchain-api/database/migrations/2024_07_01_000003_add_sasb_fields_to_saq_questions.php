<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saq_questions', function (Blueprint $table) {
            $table->uuid('sasb_topic_id')->nullable()->after('is_required');
            $table->string('sasb_metric_code', 40)->nullable()->after('sasb_topic_id')
                  ->comment('e.g. EM-IS-110a.1');

            $table->foreign('sasb_topic_id')->references('id')->on('sasb_disclosure_topics')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('saq_questions', function (Blueprint $table) {
            $table->dropForeign(['sasb_topic_id']);
            $table->dropColumn(['sasb_topic_id', 'sasb_metric_code']);
        });
    }
};
