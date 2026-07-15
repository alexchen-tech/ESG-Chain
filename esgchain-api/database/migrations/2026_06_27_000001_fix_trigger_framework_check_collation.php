<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 修復 trg_saq_questions_framework_check 的 collation 不一致 bug：
 * v_framework 變數未指定 collation，預設採連線層 collation（utf8mb4_0900_ai_ci），
 * 與 question_tags.l1_domain 欄位（utf8mb4_unicode_ci）比對時報錯。
 * 此 bug 過去因 scoring_framework 恆為 null 而從未真正觸發比對邏輯。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_saq_questions_framework_check');

        DB::unprepared("
            CREATE TRIGGER trg_saq_questions_framework_check
            BEFORE INSERT ON saq_questions
            FOR EACH ROW
            BEGIN
                DECLARE v_framework VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                DECLARE v_match_count INT DEFAULT 0;

                IF NEW.template_id IS NOT NULL AND NEW.source_bank_question_id IS NOT NULL THEN
                    SELECT t.scoring_framework INTO v_framework
                    FROM saq_templates t
                    WHERE t.id = NEW.template_id
                    LIMIT 1;

                    IF v_framework IS NOT NULL THEN
                        SELECT COUNT(*) INTO v_match_count
                        FROM question_tag_assignments qta
                        JOIN question_tags qt ON qt.id = qta.tag_id
                        WHERE qta.question_id = NEW.source_bank_question_id
                          AND qt.l1_domain = v_framework
                          AND qt.deprecated_at IS NULL;

                        IF v_match_count = 0 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Template question must have at least one TAG matching template scoring_framework';
                        END IF;
                    END IF;
                END IF;
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_saq_questions_framework_check');
    }
};
