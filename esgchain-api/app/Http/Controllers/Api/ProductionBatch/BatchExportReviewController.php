<?php

namespace App\Http\Controllers\Api\ProductionBatch;

use App\Http\Controllers\Controller;
use App\Models\BatchExportReview;
use App\Models\ProductionBatch;
use App\Services\ProductionBatch\BatchExportReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchExportReviewController extends Controller
{
    public function __construct(private readonly BatchExportReviewService $service) {}

    /** 該批次的各市場審查紀錄 */
    public function index(string $batchId): JsonResponse
    {
        $batch = ProductionBatch::findOrFail($batchId);

        return response()->json([
            'success' => true,
            'data'    => BatchExportReview::where('production_batch_id', $batch->id)
                ->orderBy('market')->get(),
        ]);
    }

    /** 設定市場並執行審查（重跑 upsert） */
    public function store(Request $request, string $batchId): JsonResponse
    {
        $validated = $request->validate([
            'market' => ['required', 'string', 'in:' . implode(',', BatchExportReview::MARKETS)],
        ]);

        $batch  = ProductionBatch::findOrFail($batchId);
        $review = $this->service->review($batch, $validated['market']);

        return response()->json([
            'success' => true,
            'data'    => $review,
            'message' => "已完成 {$validated['market']} 市場合規審查（{$review->status}）",
        ], 201);
    }

    public function destroy(string $batchId, string $reviewId): JsonResponse
    {
        BatchExportReview::where('production_batch_id', $batchId)
            ->where('id', $reviewId)->firstOrFail()->delete();

        return response()->json(['success' => true, 'message' => '審查紀錄已移除']);
    }
}
