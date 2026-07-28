<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\CAP;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalCapController extends Controller
{
    /**
     * GET /api/v1/portal/caps
     * 供應商查看自己的矯正行動清單（強制以帳號綁定的 supplier_id 過濾，不開放指定他人）。
     */
    public function index(Request $request): JsonResponse
    {
        $supplierId = $request->user()->supplier_id;
        abort_if(!$supplierId, 403, '此帳號未綁定供應商');

        $paginated = CAP::where('supplier_id', $supplierId)
            ->with(['findings', 'updates.changedBy'])
            ->withCount('attachments')
            ->orderBy('due_date')
            ->paginate(20);

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

    /**
     * GET /api/v1/portal/caps/{cap}
     */
    public function show(Request $request, CAP $cap): JsonResponse
    {
        $supplierId = $request->user()->supplier_id;
        abort_if(!$supplierId, 403, '此帳號未綁定供應商');
        abort_if($cap->supplier_id !== $supplierId, 403, '無權查看此矯正行動');

        $cap->load(['saq', 'findings', 'updates.changedBy', 'updates.attachments', 'attachments.uploadedBy']);

        return response()->json([
            'success' => true,
            'data'    => $cap,
        ]);
    }

    /**
     * POST /api/v1/portal/caps/{cap}/update
     * 供應商只能將狀態推進到 in_progress / completed，不可結案（closed）或退回 open。
     */
    public function addUpdate(Request $request, CAP $cap): JsonResponse
    {
        $supplierId = $request->user()->supplier_id;
        abort_if(!$supplierId, 403, '此帳號未綁定供應商');
        abort_if($cap->supplier_id !== $supplierId, 403, '無權操作此矯正行動');

        $validated = $request->validate([
            'status' => ['required', 'in:in_progress,completed'],
            'notes'  => ['nullable', 'string', 'max:2000'],
        ]);

        $cap->update(['status' => $validated['status']]);

        $cap->updates()->create([
            'status'     => $validated['status'],
            'notes'      => $validated['notes'] ?? null,
            'changed_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data'    => $cap->fresh(['findings', 'updates.changedBy', 'updates.attachments']),
            'message' => '狀態已更新',
        ]);
    }
}
