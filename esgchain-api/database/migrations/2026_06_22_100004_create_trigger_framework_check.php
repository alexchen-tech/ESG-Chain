<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 當從題庫（source_bank_question_id IS NOT NULL）匯入題目至有 scoring_framework 的範本時，
        // 驗證來源 bank question 是否有至少一個符合該框架的 TAG。
        // 直接新增（非從題庫匯入）的題目由應用層驗證。
        DB::unprepared("
            CREATE TRIGGER trg_saq_questions_framework_check
            BEFORE INSERT ON saq_questions
            FOR EACH ROW
            BEGIN
                DECLARE v_framework VARCHAR(50);
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
