<?php

namespace App\Http\Controllers\Api\SAQ;

use App\Http\Controllers\Controller;
use App\Models\SAQ;
use App\Models\SaqProject;
use App\Models\Supplier;
use App\Models\SupplierDisclosure;
use App\Models\SupplierDisclosureField;
use App\Services\CAP\CapAutoGenerationService;
use App\Services\Risk\RiskAutoDerivationService;
use App\Services\SAQ\SAQService;
use App\Services\SAQ\SaqScoreSnapshotService;
use App\Services\SAQ\SaqReviewerScoreService;
use App\Services\Questionnaire\QuestionnaireService;
use App\Services\Settings\SasbRequiredTopicService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SAQController extends Controller
{
    public function __construct(
        private readonly SAQService $service,
        private readonly QuestionnaireService $questionnaireService,
        private readonly SaqScoreSnapshotService $snapshotService,
        private readonly SaqReviewerScoreService $reviewerScoreService,
    ) {}

    // 列出專案下所有 SAQ
    public function indexByProject(SaqProject $project): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->listByProject($project),
            'message' => '',
        ]);
    }

    // 供應商查看自己的 SAQ 列表
    public function myList(Request $request): JsonResponse
    {
        $paginated = $this->service->listForSupplier($request->user()->supplier_id);

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
            'message' => '',
        ]);
    }

    // 查看單筆 SAQ
    public function show(SAQ $saq): JsonResponse
    {
        $saq->load(['supplier', 'project.projectQuestions', 'responses', 'reviewHistories']);

        // 依供應商 SASB 代碼動態注入 is_sasb_required 標記
        $sasbCode = $saq->supplier?->sasb_industry_code ?? null;
        if ($sasbCode) {
            $requiredSlugs = app(SasbRequiredTopicService::class)->getRequiredSlugsForCode($sasbCode);
            if (!empty($requiredSlugs) && $saq->project?->projectQuestions) {
                $saq->project->projectQuestions->transform(function ($pq) use ($requiredSlugs) {
                    $tags = is_array($pq->tags) ? $pq->tags : [];
                    $isSasbRequired = !empty(array_intersect($tags, $requiredSlugs));
                    $pq->is_sasb_required = $isSasbRequired;
                    return $pq;
                });
            }
        }

        return response()->json([
            'success' => true,
            'data' => $saq,
            'message' => '',
        ]);
    }

    // 發送問卷給供應商
    public function send(Request $request, SaqProject $project): JsonResponse
    {
        $request->validate([
            'supplier_id' => ['required', 'uuid', 'exists:suppliers,id'],
        ]);

        $supplier = Supplier::findOrFail($request->supplier_id);
        $saq = $this->service->send($project, $supplier, $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $saq,
            'message' => '問卷已發送',
        ], 201);
    }

    // 供應商提交問卷
    public function submit(Request $request, SAQ $saq): JsonResponse
    {
        $request->validate([
            'responses' => ['required', 'array'],
            'responses.*.question_id' => ['required', 'uuid'],
            'responses.*.answer' => ['nullable', 'string'],
            'responses.*.answer_options' => ['nullable', 'array'],
            'responses.*.evidence_note' => ['nullable', 'string'],
        ]);

        // 100% 必答驗證：所有 is_required=true 的題目必須有回答
        $requiredQuestionIds = \App\Models\ProjectQuestion::where('project_id', $saq->project_id)
            ->where('is_required', true)
            ->pluck('id')
            ->toArray();

        if (!empty($requiredQuestionIds)) {
            $answeredIds = collect($request->responses)
                ->filter(fn($r) => !is_null($r['answer'] ?? null) || !empty($r['answer_options'] ?? []))
                ->pluck('question_id')
                ->toArray();

            $unanswered = array_values(array_diff($requiredQuestionIds, $answeredIds));
            if (!empty($unanswered)) {
                return response()->json([
                    'success'       => false,
                    'message'       => '尚有必答題目未填寫，無法提交',
                    'unanswered_ids' => $unanswered,
                ], 422);
            }
        }

        $updated = $this->service->submit($saq, $request->responses, $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $updated,
            'message' => '問卷已提交，計分中',
        ]);
    }

    // 審查員核准
    public function approve(Request $request, SAQ $saq): JsonResponse
    {
        $request->validate(['comment' => ['nullable', 'string']]);
        $updated = $this->service->approve($saq, $request->user()->id, $request->comment);

        return response()->json([
            'success' => true,
            'data' => $updated,
            'message' => '問卷已核准',
        ]);
    }

    // 審查員退回
    public function reject(Request $request, SAQ $saq): JsonResponse
    {
        $request->validate(['comment' => ['required', 'string']]);
        $updated = $this->service->reject($saq, $request->user()->id, $request->comment);

        return response()->json([
            'success' => true,
            'data' => $updated,
            'message' => '問卷已退回',
        ]);
    }

    // 審核：開始審核
    public function startReview(Request $request, SAQ $saq): JsonResponse
    {
        $updated = $this->questionnaireService->startReview($saq, $request->user()->id);
        return response()->json(['success' => true, 'data' => $updated, 'message' => '已開始審核']);
    }

    // 審核：通過
    public function completeReview(Request $request, SAQ $saq): JsonResponse
    {
        $request->validate(['comment' => ['nullable', 'string']]);
        $updated = $this->questionnaireService->completeReview($saq, $request->user()->id, $request->comment);
        return response()->json(['success' => true, 'data' => $updated, 'message' => '審核通過']);
    }

    // 審核：退回
    public function returnReview(Request $request, SAQ $saq): JsonResponse
    {
        $request->validate(['comment' => ['required', 'string']]);
        $updated = $this->questionnaireService->returnReview($saq, $request->user()->id, $request->comment);
        return response()->json(['success' => true, 'data' => $updated, 'message' => '已退回供應商修改']);
    }

    // 審核：複核確認
    public function markReviewed(Request $request, SAQ $saq): JsonResponse
    {
        $updated = $this->questionnaireService->markReviewed($saq, $request->user()->id);
        return response()->json(['success' => true, 'data' => $updated, 'message' => '已完成複核']);
    }

    // FastAPI 計分 callback
    public function scoreCallback(Request $request, SAQ $saq): JsonResponse
    {
        $request->validate([
            'score'                                  => ['required', 'numeric'],
            'grade'                                  => ['required', 'string', 'in:A,B,C,D,E'],
            'job_id'                                 => ['required', 'string'],
            'score_e'                                => ['sometimes', 'nullable', 'numeric'],
            'score_s'                                => ['sometimes', 'nullable', 'numeric'],
            'score_g'                                => ['sometimes', 'nullable', 'numeric'],
            'category_scores'                        => ['sometimes', 'nullable', 'array'],
            'scoring_model_id'                       => ['sometimes', 'nullable', 'string'],
            'question_scores'                        => ['sometimes', 'array'],
            'question_scores.*.project_question_id' => ['required_with:question_scores', 'uuid'],
            'question_scores.*.raw_score'            => ['required_with:question_scores', 'numeric'],
            'question_scores.*.confidence'           => ['sometimes', 'nullable', 'string', 'in:high,medium,low'],
            // multi-framework 三軸欄位
            'scoring_framework'    => ['sometimes', 'nullable', 'string'],
            'iso26000_total'       => ['sometimes', 'nullable', 'numeric'],
            'iso20400_total'       => ['sometimes', 'nullable', 'numeric'],
            'geo_risk_total'       => ['sometimes', 'nullable', 'numeric'],
            'axis1_score'          => ['sometimes', 'nullable', 'numeric'],
            'axis2_score'          => ['sometimes', 'nullable', 'numeric'],
            // 六維計分欄位（assessment_version='v2' 時出現）
            'assessment_version'   => ['sometimes', 'nullable', 'string'],
            'dim_e1'               => ['sometimes', 'nullable', 'numeric'],
            'dim_e2'               => ['sometimes', 'nullable', 'numeric'],
            'dim_e3'               => ['sometimes', 'nullable', 'numeric'],
            'dim_e4'               => ['sometimes', 'nullable', 'numeric'],
            'dim_e5'               => ['sometimes', 'nullable', 'numeric'],
            'dim_e6'               => ['sometimes', 'nullable', 'numeric'],
        ]);

        // 逐題更新 raw_score + score_confidence
        if ($request->has('question_scores')) {
            foreach ($request->question_scores as $qs) {
                $updates = ['raw_score' => $qs['raw_score']];
                if (array_key_exists('confidence', $qs)) {
                    $updates['score_confidence'] = $qs['confidence'];
                }
                $saq->responses()
                    ->where('project_question_id', $qs['project_question_id'])
                    ->update($updates);
            }
        }

        $updated = $this->service->updateScore(
            $saq,
            $request->score,
            $request->grade,
            $request->job_id,
            $request->score_e,
            $request->score_s,
            $request->score_g,
            $request->scoring_model_id,
            $request->category_scores,
        );

        // status=='submitted' 表示首次計分；其他狀態（under_review 等）表示權重調整重算
        $snapshotTrigger = ($saq->status === 'submitted') ? 'submit' : 'weight_updated';
        $this->snapshotService->create($updated, $snapshotTrigger);

        // 自動推導風險評估（multi-framework 時同時寫入三軸分）
        $multiFrameworkData = [];
        $categoryScores     = [];
        if ($request->input('scoring_framework') === 'multi-framework') {
            $multiFrameworkData = [
                'iso26000_total' => $request->iso26000_total,
                'iso20400_total' => $request->iso20400_total,
                'axis1_score'    => $request->axis1_score,
                'axis2_score'    => $request->axis2_score,
            ];
            $categoryScores = (array) ($request->category_scores ?? []);
        }
        // 六維計分 callback（v2）
        if ($request->input('assessment_version') === 'v2' && $request->input('dim_e1') !== null) {
            $dims = [
                'dim_e1' => $request->dim_e1,
                'dim_e2' => $request->dim_e2,
                'dim_e3' => $request->dim_e3,
                'dim_e4' => $request->dim_e4,
                'dim_e5' => $request->dim_e5,
                'dim_e6' => $request->dim_e6,
            ];

            // 儲存六維度分數到 SAQ，並以 series.dim_weights 合成總分
            $synthScore = $this->synthesizeDimScore($updated, $dims);
            $synthGrade = $this->deriveGrade($synthScore, $updated->project?->series?->grade_thresholds ?? []);
            $updated->update(array_merge($dims, [
                'score' => $synthScore,
                'grade' => $synthGrade,
            ]));
            $updated->refresh();

            $ra = app(RiskAutoDerivationService::class)->deriveFromSaqV2($updated, $dims);
        } else {
            $ra = app(RiskAutoDerivationService::class)->deriveFromSaq($updated, $multiFrameworkData);
        }

        // 首次計分（submit）且為 multi-framework 時觸發 CAP 自動建立
        if ($snapshotTrigger === 'submit' && $ra !== null && !empty($multiFrameworkData)) {
            app(CapAutoGenerationService::class)->generateFromRisk($ra, $updated, $categoryScores);
        }

        return response()->json(['success' => true, 'data' => $updated, 'message' => '計分結果已更新']);
    }

    // 題目層覆核 — 審核員提交覆核分
    public function submitResponseReviews(Request $request, SAQ $saq): JsonResponse
    {
        $request->validate([
            'reviews'                              => ['required', 'array', 'min:1'],
            'reviews.*.project_question_id'        => ['required', 'uuid'],
            'reviews.*.reviewer_score'             => ['required', 'numeric', 'min:0', 'max:100'],
            'reviews.*.reason'                     => ['nullable', 'string'],
        ]);

        $updated = $this->reviewerScoreService->submitReviews(
            $saq,
            $request->reviews,
            $request->user()->id,
        );

        return response()->json([
            'success' => true,
            'data'    => $updated,
            'message' => '覆核分已提交，最終評分已更新',
        ]);
    }

    // 題目層覆核 — 查詢覆核分清單
    public function getResponseReviews(SAQ $saq): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $saq->responseReviews()->with('reviewer:id,name,email')->get(),
        ]);
    }

    // 計分快照 — 查詢快照列表
    public function getScoreSnapshots(SAQ $saq): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $saq->scoreSnapshots()->get(),
        ]);
    }

    // 申訴 — 開始重新審核（審核員）
    public function startReReview(Request $request, SAQ $saq): JsonResponse
    {
        $updated = $this->questionnaireService->startReReview($saq, $request->user()->id);
        return response()->json(['success' => true, 'data' => $updated, 'message' => '已開始重新審核']);
    }

    // 申訴 — 最終確認（審核員）
    public function finalize(Request $request, SAQ $saq): JsonResponse
    {
        $request->validate(['comment' => ['nullable', 'string']]);
        $updated = $this->questionnaireService->finalize($saq, $request->user()->id, $request->comment, $this->snapshotService);
        return response()->json(['success' => true, 'data' => $updated, 'message' => '問卷已終結，最終評分已鎖定']);
    }

    /**
     * GET /api/v1/saqs/{saq}/prefill-hints
     * 回傳此 SAQ 各題的 disclosure 歷史值（供前端預填用）。
     */
    public function prefillHints(Request $request, SAQ $saq): JsonResponse
    {
        $supplierId = $saq->supplier_id;

        $questions = \Illuminate\Support\Facades\DB::select("
            SELECT
                pq.id AS project_question_id,
                sq.disclosure_field_slug,
                sdf.data_type,
                sdf.unit,
                sdf.label
            FROM project_questions pq
            JOIN saq_questions sq ON pq.source_bank_question_id = sq.id
            JOIN supplier_disclosure_fields sdf ON sdf.slug = sq.disclosure_field_slug
            WHERE pq.project_id = ?
              AND sq.disclosure_field_slug IS NOT NULL
        ", [$saq->project_id]);

        if (empty($questions)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $slugs = array_unique(array_column($questions, 'disclosure_field_slug'));

        $latestDisclosures = SupplierDisclosure::where('supplier_id', $supplierId)
            ->whereIn('field_slug', $slugs)
            ->orderByDesc('period_year')
            ->get()
            ->groupBy('field_slug')
            ->map(fn ($group) => $group->first());

        $hints = [];
        foreach ($questions as $q) {
            $disc = $latestDisclosures->get($q->disclosure_field_slug);
            if (!$disc) continue;

            $hints[$q->project_question_id] = [
                'field_slug'       => $q->disclosure_field_slug,
                'data_type'        => $q->data_type,
                'unit'             => $q->unit,
                'label'            => $q->label,
                'last_period_year' => $disc->period_year,
                'last_numeric'     => $disc->numeric_value,
                'last_boolean'     => $disc->boolean_value,
                'last_text'        => $disc->text_value,
            ];
        }

        return response()->json(['success' => true, 'data' => $hints]);
    }

    // LLM 文字題評分回寫（AI Celery llm_scoring_tasks 呼叫，無 JWT）
    public function llmScoreCallback(Request $request, SAQ $saq): JsonResponse
    {
        $request->validate([
            'project_question_id' => ['required', 'uuid'],
            'llm_score'           => ['required', 'numeric', 'min:0', 'max:100'],
            'llm_score_reason'    => ['nullable', 'string', 'max:500'],
        ]);

        $pqId = $request->project_question_id;

        $saq->responses()
            ->where('project_question_id', $pqId)
            ->update([
                'llm_score'        => $request->llm_score,
                'llm_score_reason' => $request->llm_score_reason,
                'score_confidence' => 'medium',
                // LLM 完成後重算 raw_score（answer_score = llm_score / 100）
                'raw_score'        => \DB::raw("llm_score / 100.0 * (
                    SELECT weight FROM project_questions WHERE id = '$pqId' LIMIT 1
                )"),
            ]);

        return response()->json(['success' => true, 'message' => 'LLM 評分已寫入']);
    }

    /**
     * 以六維度分數與 series.dim_weights 合成 0–100 總分。
     * dim_eN 已為 0–100 scale（AI 端輸出），直接加權。
     */
    private function synthesizeDimScore(SAQ $saq, array $dims): float
    {
        $series = $saq->project?->series;
        $weights = $series?->dim_weights
            ?? app(SystemSettingsService::class)->getDimWeightDefaults();

        $keys = ['E1'=>'dim_e1','E2'=>'dim_e2','E3'=>'dim_e3','E4'=>'dim_e4','E5'=>'dim_e5','E6'=>'dim_e6'];
        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($keys as $eKey => $dimKey) {
            $val = $dims[$dimKey] ?? null;
            $w   = $weights[$eKey] ?? 0;
            if ($val !== null && $w > 0) {
                $weightedSum += $val * $w;
                $weightTotal += $w;
            }
        }

        return $weightTotal > 0 ? round($weightedSum / $weightTotal, 2) : 0.0;
    }

    private function deriveGrade(float $score, array $thresholds): string
    {
        if ($score >= ($thresholds['A'] ?? 80.0)) return 'A';
        if ($score >= ($thresholds['B'] ?? 60.0)) return 'B';
        if ($score >= ($thresholds['C'] ?? 40.0)) return 'C';
        if ($score >= ($thresholds['D'] ?? 20.0)) return 'D';
        return 'E';
    }
}
