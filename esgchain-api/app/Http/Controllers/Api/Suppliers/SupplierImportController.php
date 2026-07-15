<?php

namespace App\Http\Controllers\Api\Suppliers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\AvlImportRequest;
use App\Models\SupplierImport;
use App\Services\Suppliers\SupplierAvlImportService;
use App\Services\Suppliers\SupplierImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupplierImportController extends Controller
{
    public function __construct(
        private SupplierImportService $service,
        private SupplierAvlImportService $avlService,
    ) {}

    public function importAvl(AvlImportRequest $request): JsonResponse
    {
        try {
            $result = $this->avlService->importFromCsv($request->file('file'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $result], 201);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        try {
            $rows = $this->service->parseCsv($request->file('file'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $batchId = Str::uuid()->toString();
        $this->service->ingestBatch($rows, $batchId);
        $stats = $this->service->cleanseBatch($batchId);

        return response()->json([
            'success'   => true,
            'batch_id'  => $batchId,
            'total'     => count($rows),
            'cleansed'  => $stats['cleansed'],
            'rejected'  => $stats['rejected'],
        ], 201);
    }

    public function status(string $batchId): JsonResponse
    {
        $counts = SupplierImport::where('batch_id', $batchId)
            ->selectRaw('cleanse_status, count(*) as cnt')
            ->groupBy('cleanse_status')
            ->pluck('cnt', 'cleanse_status')
            ->toArray();

        return response()->json([
            'success'  => true,
            'batch_id' => $batchId,
            'total'    => array_sum($counts),
            'staged'   => $counts['staged'] ?? 0,
            'cleansed' => $counts['cleansed'] ?? 0,
            'rejected' => $counts['rejected'] ?? 0,
            'exempt'   => $counts['exempt'] ?? 0,
            'approved' => $counts['approved'] ?? 0,
        ]);
    }

    public function list(Request $request, string $batchId): JsonResponse
    {
        $query = SupplierImport::where('batch_id', $batchId);
        if ($request->cleanse_status) {
            $query->where('cleanse_status', $request->cleanse_status);
        }
        $items = $query->orderBy('created_at')->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function update(Request $request, string $batchId, SupplierImport $import): JsonResponse
    {
        $validated = $request->validate([
            'primary_email' => ['sometimes', 'nullable', 'string'],
            'notes'         => ['sometimes', 'nullable', 'string'],
        ]);

        // 補齊 email 後重新跑 L1 清洗
        if (isset($validated['primary_email']) && $import->cleanse_status === 'rejected') {
            $import->fill($validated);
            $import->cleanse_status = 'staged';
            $import->failure_codes = null;
            $import->save();

            $this->service->cleanseBatch($batchId);
            $import->refresh();
        } else {
            $import->update($validated);
        }

        return response()->json(['success' => true, 'data' => $import->fresh()]);
    }

    public function exempt(Request $request, string $batchId, SupplierImport $import): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ]);

        $import->update([
            'cleanse_status' => 'exempt',
            'notes'          => $validated['notes'],
        ]);

        return response()->json(['success' => true, 'data' => $import->fresh()]);
    }

    public function approve(string $batchId): JsonResponse
    {
        $result = $this->service->approveBatch($batchId);

        return response()->json([
            'success' => true,
            'message' => "已放行 {$result['approved_count']} 筆供應商",
            'data'    => $result,
        ]);
    }
}
