<?php

namespace App\Http\Controllers\Api\PCF;

use App\Http\Controllers\Controller;
use App\Services\PCF\PcfRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PcfRequestController extends Controller
{
    public function __construct(private PcfRequestService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = array_filter([
            'supplier_id' => $request->supplier_id,
            'status'      => $request->status,
            'period_year' => $request->period_year,
            'due_before'  => $request->due_before,
            'page'        => $request->page,
            'per_page'    => $request->per_page,
        ], fn($v) => $v !== null);

        $paginated = $this->service->list($filters);

        return response()->json([
            'success' => true,
            'data'    => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    public function batchCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batches'                     => ['required', 'array', 'min:1'],
            'batches.*.supplier_id'       => ['required', 'uuid', 'exists:suppliers,id'],
            'batches.*.bom_line_ids'      => ['required', 'array', 'min:1'],
            'batches.*.bom_line_ids.*'    => ['required', 'uuid'],
            'batches.*.period_start'      => ['required', 'date'],
            'batches.*.period_end'        => ['required', 'date', 'after:batches.*.period_start'],
            'batches.*.due_date'          => ['required', 'date'],
            'batches.*.saq_round_id'      => ['nullable', 'uuid'],
        ], [
            'batches.required'              => '請提供至少一筆請求',
            'batches.*.supplier_id.exists'  => '供應商不存在',
        ]);

        $result = $this->service->batchCreate($validated['batches']);

        return response()->json(['success' => true, 'data' => $result], 201);
    }
}
