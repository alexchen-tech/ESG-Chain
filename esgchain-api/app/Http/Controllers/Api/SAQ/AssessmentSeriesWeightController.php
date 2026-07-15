<?php

namespace App\Http\Controllers\Api\SAQ;

use App\Http\Controllers\Controller;
use App\Models\AssessmentSeries;
use App\Services\SAQ\AssessmentSeriesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentSeriesWeightController extends Controller
{
    public function __construct(private readonly AssessmentSeriesService $service) {}

    public function index(AssessmentSeries $series): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getWeights($series),
            'message' => '',
        ]);
    }

    public function update(Request $request, AssessmentSeries $series): JsonResponse
    {
        $request->validate([
            'weights'                                      => ['required', 'array'],
            'weights.*.source_template_question_id'       => ['required', 'uuid'],
            'weights.*.weight'                             => ['required', 'numeric', 'min:0'],
        ]);

        $result = $this->service->setWeights($series, $request->weights);

        return response()->json(['success' => true, 'data' => $result, 'message' => 'Weight 設定已儲存']);
    }
}
