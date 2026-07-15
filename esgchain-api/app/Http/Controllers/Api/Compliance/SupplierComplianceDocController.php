<?php

namespace App\Http\Controllers\Api\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierComplianceDoc;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupplierComplianceDocController extends Controller
{
    private const VALID_DOC_TYPES = [
        'UFLPA_DECLARATION', 'EUDR_DDS', 'CMRT', 'SDS', 'CE_DOC', 'ORIGIN_CERT', 'OTHER',
    ];

    public function index(Request $request, Supplier $supplier): JsonResponse
    {
        $query = $supplier->complianceDocs()->with('tradeGood');

        // status 篩選需在記憶體中過濾（動態計算）
        $docs = $query->orderByDesc('created_at')->get();

        if ($statusFilter = $request->query('status')) {
            $docs = $docs->filter(fn($d) => $d->status === $statusFilter)->values();
        }

        return response()->json(['success' => true, 'data' => $docs]);
    }

    public function store(Request $request, Supplier $supplier): JsonResponse
    {
        // 供應商只能為自己上傳
        $user = $request->user();
        if (in_array('supplier', $user->getRoleNames()->toArray()) || in_array('sup_esg', $user->getRoleNames()->toArray())) {
            abort_if($user->supplier_id !== $supplier->id, 403);
        }

        $validated = $request->validate([
            'doc_type'      => ['required', 'string', 'in:' . implode(',', self::VALID_DOC_TYPES)],
            'file'          => ['required', 'file', 'max:10240'],
            'trade_good_id' => ['nullable', 'uuid', 'exists:trade_goods,id'],
            'issued_at'     => ['nullable', 'date'],
            'expires_at'    => ['nullable', 'date'],
            'notes'         => ['nullable', 'string'],
        ]);

        $file     = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $path     = $file->store("compliance-docs/{$supplier->id}", 'local');

        $doc = $supplier->complianceDocs()->create([
            'doc_type'      => $validated['doc_type'],
            'trade_good_id' => $validated['trade_good_id'] ?? null,
            'file_path'     => $path,
            'file_name'     => $fileName,
            'issued_at'     => $validated['issued_at'] ?? null,
            'expires_at'    => $validated['expires_at'] ?? null,
            'notes'         => $validated['notes'] ?? null,
        ]);

        return response()->json(['success' => true, 'data' => $doc], 201);
    }

    public function destroy(Request $request, SupplierComplianceDoc $complianceDoc): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $user->hasRole('admin');

        if (!$isAdmin) {
            abort_if(
                in_array('supplier', $user->getRoleNames()->toArray()) && $user->supplier_id !== $complianceDoc->supplier_id,
                403
            );
        }

        $complianceDoc->delete();

        return response()->json(['success' => true, 'message' => '文件已刪除']);
    }

    public function download(SupplierComplianceDoc $complianceDoc): mixed
    {
        $path = $complianceDoc->file_path;
        if (!$path || !Storage::disk('local')->exists($path)) {
            return response()->json(['success' => false, 'message' => '文件不存在'], 404);
        }
        $fullPath = Storage::disk('local')->path($path);
        $fileName = $complianceDoc->file_name ?? basename($path);
        return response()->download($fullPath, $fileName);
    }

    public function verify(Request $request, SupplierComplianceDoc $complianceDoc): JsonResponse
    {
        $missingExpiry = $complianceDoc->expires_at === null;

        $validated = $request->validate([
            'expires_at' => [$missingExpiry ? 'required' : 'nullable', 'date'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ], [
            'expires_at.required' => '有效期為必填欄位',
        ]);

        $updates = [
            'verified_at' => Carbon::now(),
            'verified_by' => $request->user()->id,
        ];

        if ($missingExpiry && isset($validated['expires_at'])) {
            $updates['expires_at'] = $validated['expires_at'];
        }

        if (array_key_exists('notes', $validated)) {
            $updates['notes'] = $validated['notes'];
        }

        $complianceDoc->update($updates);

        return response()->json(['success' => true, 'data' => $complianceDoc->fresh()]);
    }

    public function unverify(SupplierComplianceDoc $complianceDoc): JsonResponse
    {
        $complianceDoc->update([
            'verified_at' => null,
            'verified_by' => null,
        ]);

        return response()->json(['success' => true, 'data' => $complianceDoc->fresh()]);
    }
}
