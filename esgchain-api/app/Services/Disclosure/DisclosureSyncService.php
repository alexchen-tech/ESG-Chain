<?php

namespace App\Services\Disclosure;

use App\Models\SAQ;
use App\Models\SupplierDisclosure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DisclosureSyncService
{
    /**
     * SAQ 評分完成後，將有 disclosure_field_slug 映射的回答同步至 supplier_disclosures。
     * 例外只 log，不影響主流程。
     */
    public function syncFromSaq(SAQ $saq): void
    {
        try {
            $this->doSync($saq);
        } catch (\Throwable $e) {
            Log::error('DisclosureSyncService::syncFromSaq failed', [
                'saq_id'      => $saq->id,
                'supplier_id' => $saq->supplier_id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    private function doSync(SAQ $saq): void
    {
        $periodYear = ($saq->submitted_at ?? $saq->updated_at)?->year ?? now()->year;

        $rows = DB::select("
            SELECT
                sr.answer,
                sr.answer_options,
                tq.question_type,
                bq.disclosure_field_slug,
                sdf.data_type,
                sdf.unit
            FROM saq_responses sr
            JOIN saq_questions tq ON sr.question_id = tq.id
            JOIN saq_questions bq ON tq.source_bank_question_id = bq.id
            JOIN supplier_disclosure_fields sdf ON sdf.slug = bq.disclosure_field_slug
            WHERE sr.saq_id = ?
              AND bq.disclosure_field_slug IS NOT NULL
        ", [$saq->id]);

        foreach ($rows as $row) {
            [$numericValue, $booleanValue, $textValue] = $this->extractValues($row);

            if ($numericValue === null && $booleanValue === null && $textValue === null) {
                continue;
            }

            SupplierDisclosure::updateOrCreate(
                [
                    'supplier_id' => $saq->supplier_id,
                    'field_slug'  => $row->disclosure_field_slug,
                    'period_year' => $periodYear,
                ],
                [
                    'numeric_value' => $numericValue,
                    'boolean_value' => $booleanValue,
                    'text_value'    => $textValue,
                    'source'        => 'saq_sync',
                    'source_saq_id' => $saq->id,
                ]
            );
        }
    }

    private function extractValues(object $row): array
    {
        $numericValue = null;
        $booleanValue = null;
        $textValue    = null;

        match ($row->data_type) {
            'numeric' => $numericValue = is_numeric($row->answer) ? (float) $row->answer : null,
            'boolean' => $booleanValue = in_array(strtolower((string) $row->answer), ['1', 'true', 'yes', '是', '有'], true),
            'single_choice' => $textValue = $row->answer ?: null,
            default => null,
        };

        return [$numericValue, $booleanValue, $textValue];
    }
}
