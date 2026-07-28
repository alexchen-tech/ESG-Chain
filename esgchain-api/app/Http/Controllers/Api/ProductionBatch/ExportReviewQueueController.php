<?php

namespace App\Http\Controllers\Api\ProductionBatch;

use App\Http\Controllers\Controller;
use App\Services\ProductionBatch\ExportReviewQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportReviewQueueController extends Controller
{
    public function __construct(private readonly ExportReviewQueueService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters   = $request->only(['market', 'status', 'production_date_from', 'production_date_to', 'per_page']);
        $paginator = $this->service->list($filters);

        return response()->json([
            'success'    => true,
            'data'       => $paginator->items(),
            'pagination' => [
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }
}
