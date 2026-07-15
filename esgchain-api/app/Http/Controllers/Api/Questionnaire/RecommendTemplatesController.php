<?php

namespace App\Http\Controllers\Api\Questionnaire;

use App\Http\Controllers\Controller;
use App\Services\Questionnaire\QuestionnaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendTemplatesController extends Controller
{
    public function __construct(private QuestionnaireService $service) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_ids'   => ['required', 'array', 'min:1'],
            'supplier_ids.*' => ['uuid', 'exists:suppliers,id'],
        ]);

        $recommendations = $this->service->recommendTemplates($validated['supplier_ids']);

        return response()->json(['success' => true, 'data' => $recommendations]);
    }
}
