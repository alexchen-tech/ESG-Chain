<?php

namespace App\Http\Controllers\Api\ProductionBatch;

use App\Http\Controllers\Controller;
use App\Services\ProductionBatch\ProductionBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionBatchImportController extends Controller
{
    public function __construct(private ProductionBatchService $service) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        $headers = array_map('trim', fgetcsv($handle));
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) continue;
            $rows[] = array_combine($headers, array_map('trim', $row));
        }
        fclose($handle);

        $result = $this->service->importFromCsv($rows);

        return response()->json(['success' => true, 'data' => $result]);
    }
}
