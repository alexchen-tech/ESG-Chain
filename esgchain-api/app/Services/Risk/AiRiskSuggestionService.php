<?php

namespace App\Services\Risk;

use App\Models\RiskAssessment;
use App\Models\SAQ;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiRiskSuggestionService
{
    private string $aiUrl;

    public function __construct()
    {
        $this->aiUrl = rtrim(config('services.ai.url', env('AI_SERVICE_URL', 'http://esgchain-ai:8000')), '/');
    }

    /**
     * 取得 AI 風險建議。
     * 若 $force=false 且最新 RA 已有快取，直接回傳快取。
     */
    public function getSuggestion(string $supplierId, bool $force = false, string $bearerToken = ''): array
    {
        $supplier = Supplier::findOrFail($supplierId);

        $ra = RiskAssessment::where('supplier_id', $supplierId)
            ->orderBy('assessed_at', 'desc')
            ->first();

        if (!$ra) {
            abort(422, '尚無風險評估資料，無法產生建議');
        }

        if (!$force && $ra->ai_suggestion) {
            return [
                'cached'       => true,
                'generated_at' => $ra->ai_generated_at?->toIso8601String(),
                'suggestion'   => json_decode($ra->ai_suggestion, true),
            ];
        }

        $suggestion = $this->callAiService($supplier, $ra, $bearerToken);

        $ra->ai_suggestion   = json_encode($suggestion);
        $ra->ai_generated_at = Carbon::now();
        $ra->save();

        return [
            'cached'       => false,
            'generated_at' => $ra->ai_generated_at->toIso8601String(),
            'suggestion'   => $suggestion,
        ];
    }

    private function callAiService(Supplier $supplier, RiskAssessment $ra, string $bearerToken = ''): array
    {
        $linkedSaq = $ra->source_saq_id
            ? SAQ::find($ra->source_saq_id, ['score', 'grade', 'submitted_at'])
            : null;

        $dimLabels = [
            'E1' => 'ESG整體', 'E2' => '永續採購（ISO20400）', 'E3' => '社會責任（ISO26000）',
            'E4' => '地緣政治風險', 'E5' => '供應鏈安全（ISO28000）', 'E6' => '產品合規',
        ];

        $payload = [
            'supplier' => [
                'name'          => $supplier->name,
                'country_code'  => $supplier->country_code,
                'tier'          => $supplier->tier,
                'industry_name' => null,
            ],
            'latest_ra' => [
                'assessed_at'    => $ra->assessed_at?->toIso8601String(),
                'source_type'    => $ra->source_type ?? 'saq',
                'assessment_version' => $ra->assessment_version ?? 'v3',
                'six_dims' => [
                    ['key' => 'E1', 'label' => $dimLabels['E1'], 'score' => $ra->dim_e1],
                    ['key' => 'E2', 'label' => $dimLabels['E2'], 'score' => $ra->dim_e2],
                    ['key' => 'E3', 'label' => $dimLabels['E3'], 'score' => $ra->dim_e3],
                    ['key' => 'E4', 'label' => $dimLabels['E4'], 'score' => $ra->dim_e4],
                    ['key' => 'E5', 'label' => $dimLabels['E5'], 'score' => $ra->dim_e5],
                    ['key' => 'E6', 'label' => $dimLabels['E6'], 'score' => $ra->dim_e6],
                ],
            ],
            'latest_saq' => $linkedSaq ? [
                'grade'        => $linkedSaq->grade,
                'score'        => $linkedSaq->score,
                'submitted_at' => $linkedSaq->submitted_at?->toIso8601String(),
            ] : null,
            'open_cap_count' => \App\Models\CAP::where('source_type', 'risk_assessment')
                ->where('source_id', $ra->id)
                ->where('status', 'open')
                ->count(),
        ];

        $response = Http::timeout(30)
            ->withHeaders(['Authorization' => "Bearer {$bearerToken}"])
            ->post("{$this->aiUrl}/ai/v1/risk-suggestion", $payload);

        if (!$response->successful()) {
            Log::error('AiRiskSuggestionService: AI service error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            abort(502, 'AI 服務暫時無法使用，請稍後再試');
        }

        return $response->json();
    }
}
