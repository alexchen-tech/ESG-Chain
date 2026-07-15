<?php

namespace App\Services\Risk;

use App\Models\RiskAssessment;
use App\Models\SAQ;
use Illuminate\Support\Carbon;

class RiskAutoDerivationService
{
    /**
     * 六維計分完成後建立 v3 RiskAssessment。
     * 直接存 dim_e1–e6，不執行四軸 D6 投影公式。
     *
     * @param  array{dim_e1?: float|null, ..., dim_e6?: float|null}  $dims
     */
    public function deriveFromSaq(SAQ $saq, array $dims): ?RiskAssessment
    {
        if (($dims['dim_e1'] ?? null) === null) {
            return null;
        }

        $data = [
            'supplier_id'        => $saq->supplier_id,
            'assessed_at'        => Carbon::now(),
            'assessed_by'        => null,
            'notes'              => "自動從 SAQ {$saq->id} 推導（v3）",
            'source_saq_id'      => $saq->id,
            'source_type'        => 'saq',
            'source_id'          => $saq->id,
            'dim_e1'             => $dims['dim_e1'] ?? null,
            'dim_e2'             => $dims['dim_e2'] ?? null,
            'dim_e3'             => $dims['dim_e3'] ?? null,
            'dim_e4'             => $dims['dim_e4'] ?? null,
            'dim_e5'             => $dims['dim_e5'] ?? null,
            'dim_e6'             => $dims['dim_e6'] ?? null,
            'assessment_version' => 'v3',
        ];

        // upsert 防重複（同一 SAQ 只保留一筆 RA）
        $existing = RiskAssessment::where('source_saq_id', $saq->id)
            ->where('source_type', 'saq')
            ->first();

        if ($existing) {
            $existing->update($data);
            return $existing->fresh();
        }

        return RiskAssessment::create($data);
    }
}
