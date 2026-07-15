<?php

namespace App\Http\Controllers\Api\SAQ;

use App\Http\Controllers\Controller;
use App\Models\AssessmentSeries;
use App\Services\SAQ\AssessmentSeriesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentSeriesComparisonController extends Controller
{
    public function __construct(private readonly AssessmentSeriesService $service) {}

    public function show(Request $request, AssessmentSeries $series): JsonResponse
    {
        $request->validate([
            'supplier_ids'   => ['sometimes', 'array'],
            'supplier_ids.*' => ['uuid'],
        ]);

        $supplierIds = $request->input('supplier_ids', []);
        $data = $this->service->getComparison($series, $supplierIds);

        return response()->json(['success' => true, 'data' => $data, 'message' => '']);
    }
}
