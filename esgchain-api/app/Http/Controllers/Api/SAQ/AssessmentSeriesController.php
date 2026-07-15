<?php

namespace App\Http\Controllers\Api\SAQ;

use App\Http\Controllers\Controller;
use App\Models\AssessmentSeries;
use App\Services\SAQ\AssessmentSeriesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentSeriesController extends Controller
{
    public function __construct(private readonly AssessmentSeriesService $service) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->list(),
            'message' => '',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'template_id' => ['required', 'uuid', 'exists:saq_templates,id'],
        ]);

        $series = $this->service->create($request->only(['name', 'description', 'template_id']), $request->user()->id);

        return response()->json(['success' => true, 'data' => $series->load('template'), 'message' => '系列已建立'], 201);
    }

    public function show(AssessmentSeries $series): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->show($series),
            'message' => '',
        ]);
    }

    public function update(Request $request, AssessmentSeries $series): JsonResponse
    {
        $request->validate([
            'name'        => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            // template_id 不允許修改（系列建立後框架固定）
        ]);

        if ($request->has('template_id') && $request->template_id !== $series->template_id) {
            return response()->json([
                'success' => false,
                'message' => '評核系列建立後不可更換範本（template_id）',
            ], 422);
        }

        $updated = $this->service->update($series, $request->all());

        return response()->json(['success' => true, 'data' => $updated->load('template'), 'message' => '已更新']);
    }

    public function archive(AssessmentSeries $series): JsonResponse
    {
        $updated = $this->service->archive($series);

        return response()->json(['success' => true, 'data' => $updated, 'message' => '系列已封存']);
    }

    public function getProjects(AssessmentSeries $series): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getProjects($series),
            'message' => '',
        ]);
    }

    public function scoringConfig(AssessmentSeries $series): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getScoringConfig($series->id),
        ]);
    }

    public function updateScoringConfig(Request $request, AssessmentSeries $series): JsonResponse
    {
        $request->validate([
            'pillar_weights'            => ['sometimes', 'nullable', 'array'],
            'pillar_weights.*'          => ['numeric', 'min:0', 'max:1'],
            'grade_thresholds'          => ['sometimes', 'nullable', 'array'],
            'grade_thresholds.A'        => ['required_with:grade_thresholds', 'numeric'],
            'grade_thresholds.B'        => ['required_with:grade_thresholds', 'numeric'],
            'grade_thresholds.C'        => ['required_with:grade_thresholds', 'numeric'],
            'grade_thresholds.D'        => ['required_with:grade_thresholds', 'numeric'],
            'dim_weights'               => ['sometimes', 'nullable', 'array'],
            'dim_weights.E1'            => ['required_with:dim_weights', 'numeric', 'min:0', 'max:1'],
            'dim_weights.E2'            => ['required_with:dim_weights', 'numeric', 'min:0', 'max:1'],
            'dim_weights.E3'            => ['required_with:dim_weights', 'numeric', 'min:0', 'max:1'],
            'dim_weights.E4'            => ['required_with:dim_weights', 'numeric', 'min:0', 'max:1'],
            'dim_weights.E5'            => ['required_with:dim_weights', 'numeric', 'min:0', 'max:1'],
            'dim_weights.E6'            => ['required_with:dim_weights', 'numeric', 'min:0', 'max:1'],
            'e4_objective_ratio'        => ['sometimes', 'numeric', 'min:0', 'max:1'],
        ]);

        try {
            $updated = $this->service->updateScoringConfig($series->id, $request->only(['pillar_weights', 'grade_thresholds', 'dim_weights', 'e4_objective_ratio']));
            return response()->json(['success' => true, 'data' => $updated, 'message' => '計分設定已儲存']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
