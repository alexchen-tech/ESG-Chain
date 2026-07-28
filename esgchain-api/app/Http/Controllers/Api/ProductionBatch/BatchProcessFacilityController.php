<?php

namespace App\Http\Controllers\Api\ProductionBatch;

use App\Http\Controllers\Controller;
use App\Models\BatchProcessFacility;
use App\Models\ProductionBatch;
use App\Services\Compliance\ProductUpstreamResolver;
use App\Services\ProductionBatch\BatchProcessDueDiligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 批次×製程類型→實際供應商/廠區的選定機制。候選供應商清單完全由
 * ProductUpstreamResolver::batchProcessTypes() 推導（該批次 BOM 涉及的核可供應商
 * facility_type 聯集），不允許選定候選清單以外的供應商。
 * 見 openspec/changes/batch-process-facility-selection/design.md。
 */
class BatchProcessFacilityController extends Controller
{
    public function __construct(
        private readonly ProductUpstreamResolver $upstream,
        private readonly BatchProcessDueDiligenceService $dueDiligenceService,
    ) {}

    public function index(string $batchId): JsonResponse
    {
        $batch = ProductionBatch::findOrFail($batchId);
        $product = $batch->salesProduct;

        $data = $product
            ? $this->upstream->batchProcessTypes($batch, $product)->values()->toArray()
            : [];

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request, string $batchId): JsonResponse
    {
        $validated = $request->validate([
            'process_type'          => ['required', 'string'],
            'supplier_id'           => ['required', 'string'],
            'supplier_facility_id'  => ['nullable', 'string'],
        ]);

        $batch = ProductionBatch::findOrFail($batchId);
        $product = $batch->salesProduct;

        if (!$product) {
            throw ValidationException::withMessages([
                'process_type' => '批次無對應銷售產品，無法選定製程供應商',
            ]);
        }

        $processTypes = $this->upstream->batchProcessTypes($batch, $product);
        $processEntry = $processTypes->firstWhere('process_type', $validated['process_type']);

        if (!$processEntry) {
            throw ValidationException::withMessages([
                'process_type' => '此製程類型不在該批次 BOM 涉及的候選清單內',
            ]);
        }

        $candidate = collect($processEntry['candidates'])
            ->firstWhere('supplier_id', $validated['supplier_id']);

        if (!$candidate) {
            throw ValidationException::withMessages([
                'supplier_id' => '此供應商不在該製程類型的候選清單內',
            ]);
        }

        if (!empty($validated['supplier_facility_id'])) {
            $facilityMatches = collect($processEntry['candidates'])
                ->contains(fn ($c) => $c['supplier_id'] === $validated['supplier_id']
                    && $c['facility_id'] === $validated['supplier_facility_id']);

            if (!$facilityMatches) {
                throw ValidationException::withMessages([
                    'supplier_facility_id' => '此廠區不屬於該候選供應商',
                ]);
            }
        }

        $record = BatchProcessFacility::updateOrCreate(
            [
                'production_batch_id' => $batchId,
                'process_type'        => $validated['process_type'],
            ],
            [
                'supplier_id'          => $validated['supplier_id'],
                'supplier_facility_id' => $validated['supplier_facility_id'] ?? $candidate['facility_id'],
            ],
        );

        return response()->json([
            'success' => true,
            'data'    => $record,
            'message' => '已選定製程供應商',
        ]);
    }

    /**
     * 批次已選定的製程供應商 × 既有 SAQ 六維風險評分（環境管理/社會責任）串接查詢。
     * 純唯讀，不影響 gateCheck()/出口審查。見 process-due-diligence-saq-linkage design.md。
     */
    public function dueDiligence(string $batchId): JsonResponse
    {
        $batch = ProductionBatch::findOrFail($batchId);
        $product = $batch->salesProduct;

        $data = $this->dueDiligenceService->build($batch, $product);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function destroy(string $batchId, string $id): JsonResponse
    {
        BatchProcessFacility::where('production_batch_id', $batchId)
            ->where('id', $id)->firstOrFail()->delete();

        return response()->json(['success' => true, 'message' => '已清除選定，回到待選定狀態']);
    }
}
