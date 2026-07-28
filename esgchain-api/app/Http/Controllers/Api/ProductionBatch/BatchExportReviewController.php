<?php

namespace App\Http\Controllers\Api\ProductionBatch;

use App\Http\Controllers\Controller;
use App\Models\BatchExportReview;
use App\Models\MarketComplianceRule;
use App\Models\ProductionBatch;
use App\Services\ProductionBatch\BatchExportReviewService;
use App\Services\ProductionBatch\BatchPassportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchExportReviewController extends Controller
{
    public function __construct(
        private readonly BatchExportReviewService $service,
        private readonly BatchPassportService $passportService,
    ) {}

    /** 該批次的各市場審查紀錄，附 possibly_stale 提示（審查後供應商文件是否有變動） */
    public function index(string $batchId): JsonResponse
    {
        $batch = ProductionBatch::findOrFail($batchId);

        return response()->json([
            'success' => true,
            'data'    => $this->service->listWithStaleness($batch),
        ]);
    }

    /** 設定市場（可選法規範疇）並執行審查（重跑 upsert） */
    public function store(Request $request, string $batchId): JsonResponse
    {
        $validated = $request->validate([
            'market'  => ['required', 'string', 'in:' . implode(',', BatchExportReview::MARKETS)],
            'program' => ['nullable', 'string', 'in:' . implode(',', MarketComplianceRule::PROGRAMS)],
        ]);

        $batch   = ProductionBatch::findOrFail($batchId);
        $program = $validated['program'] ?? null;
        $review  = $this->service->review($batch, $validated['market'], $program);

        $programLabel = $program ? "（{$program}）" : '';

        return response()->json([
            'success' => true,
            'data'    => $review,
            'message' => "已完成 {$validated['market']}{$programLabel} 市場合規審查（{$review->status}）",
        ], 201);
    }

    public function destroy(string $batchId, string $reviewId): JsonResponse
    {
        BatchExportReview::where('production_batch_id', $batchId)
            ->where('id', $reviewId)->firstOrFail()->delete();

        return response()->json(['success' => true, 'message' => '審查紀錄已移除']);
    }

    /** 出貨關卡查詢：該批次×市場「已存在」的審查結論，供出貨分配時做通行判斷 */
    public function gateCheck(string $batchId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'market' => ['required', 'string', 'in:' . implode(',', BatchExportReview::MARKETS)],
        ]);

        $batch = ProductionBatch::findOrFail($batchId);
        $gate  = $this->service->gateCheck($batch, $validated['market']);

        return response()->json(['success' => true, 'data' => $gate]);
    }

    /** 產出該批次×市場的出口合規文件草稿（EUDR DDS 等） */
    public function ddsDraft(Request $request, string $batchId): JsonResponse
    {
        $validated = $request->validate([
            'market' => ['required', 'string', 'in:' . implode(',', BatchExportReview::MARKETS)],
        ]);

        $batch = ProductionBatch::findOrFail($batchId);
        $draft = $this->service->ddsDraft($batch, $validated['market']);

        return response()->json(['success' => true, 'data' => $draft]);
    }

    /** 批次「Batch Passport」：彙總產品身分/碳足跡/循環經濟/合規文件/原料溯源/出口審查的唯讀 JSON */
    public function passport(string $batchId): JsonResponse
    {
        $batch = ProductionBatch::findOrFail($batchId);

        return response()->json(['success' => true, 'data' => $this->passportService->build($batch)]);
    }
}
