<?php

namespace App\Http\Controllers\Api\Questionnaire;

use App\Http\Controllers\Controller;
use App\Models\SAQ;
use App\Services\Questionnaire\QuestionnaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionnaireController extends Controller
{
    public function __construct(
        private readonly QuestionnaireService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = $this->service->list($request->only([
            'status', 'supplier_id', 'template_id', 'project_id', 'page', 'per_page', 'overdue',
        ]));

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

    public function counts(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->counts(),
            'message' => '',
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => '請透過問卷專案發送問卷',
        ], 410);
    }

    public function show(SAQ $questionnaire): JsonResponse
    {
        $this->checkSupplierReadLock($questionnaire);

        return response()->json([
            'success' => true,
            'data' => $questionnaire->load(['supplier', 'project.template', 'project.projectQuestions.bankQuestion.questionTags', 'responses', 'reviewHistories.actor', 'reviewer']),
            'message' => '',
        ]);
    }

    public function update(Request $request, SAQ $questionnaire): JsonResponse
    {
        $this->checkSupplierWriteLock($request, $questionnaire);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        $updated = $this->service->update($questionnaire, $validated['answers']);

        return response()->json([
            'success' => true,
            'data' => $updated,
            'message' => '草稿已儲存',
        ]);
    }

    public function submit(Request $request, SAQ $questionnaire): JsonResponse
    {
        $this->checkSupplierWriteLock($request, $questionnaire);
        $updated = $this->service->submit($questionnaire, $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $updated,
            'message' => '問卷已提交',
        ]);
    }

    public function startReview(Request $request, SAQ $questionnaire): JsonResponse
    {
        $updated = $this->service->startReview($questionnaire, $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $updated,
            'message' => '審核已開始',
        ]);
    }

    public function completeReview(Request $request, SAQ $questionnaire): JsonResponse
    {
        $request->validate(['comment' => ['nullable', 'string']]);
        $updated = $this->service->completeReview($questionnaire, $request->user()->id, $request->comment);

        return response()->json([
            'success' => true,
            'data' => $updated,
            'message' => '審核已通過',
        ]);
    }

    public function returnReview(Request $request, SAQ $questionnaire): JsonResponse
    {
        $request->validate(['comment' => ['required', 'string', 'min:1']]);
        $updated = $this->service->returnReview($questionnaire, $request->user()->id, $request->comment);

        return response()->json([
            'success' => true,
            'data' => $updated,
            'message' => '問卷已退回修改',
        ]);
    }

    public function markReviewed(Request $request, SAQ $questionnaire): JsonResponse
    {
        $updated = $this->service->markReviewed($questionnaire, $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $updated,
            'message' => '問卷已標記複核完成',
        ]);
    }

    private function checkSupplierWriteLock(Request $request, SAQ $questionnaire): void
    {
        $supplierRoles = ['supplier', 'sup_esg'];
        $role = $request->user()->getRoleNames()->first();

        if (in_array($role, $supplierRoles) && $questionnaire->status === 'under_review') {
            abort(403, json_encode([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => '問卷審核中，暫時無法編輯',
            ]));
        }

        if (!$questionnaire->is_editable) {
            abort(403, json_encode([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => '問卷已完成，無法編輯',
            ]));
        }
    }

    // 供應商申訴（completed 後 7 天內）
    public function dispute(Request $request, SAQ $questionnaire): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string']]);
        $updated = $this->service->dispute($questionnaire, $request->user()->id, $request->reason);
        return response()->json(['success' => true, 'data' => $updated, 'message' => '申訴已提交']);
    }

    private function checkSupplierReadLock(SAQ $questionnaire): void
    {
        // under_review 時供應商讀取也回 403（spec 定義）
        if (auth()->user()?->getRoleNames()->contains(fn($r) => in_array($r, ['supplier', 'sup_esg']))
            && $questionnaire->status === 'under_review') {
            abort(403, json_encode([
                'success' => false,
                'error_code' => 'FORBIDDEN',
                'message' => '問卷審核中，暫時無法存取',
            ]));
        }
    }
}
