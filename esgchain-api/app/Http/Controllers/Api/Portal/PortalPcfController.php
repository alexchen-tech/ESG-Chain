<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\PcfRequest;
use App\Models\PcfRequestLine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalPcfController extends Controller
{
    /**
     * GET /api/v1/portal/pcf-request-lines?status=pending
     * 供 Portal 首頁 PCF 任務區使用
     */
    public function requestLines(Request $request): JsonResponse
    {
        $supplierId = auth()->user()->supplier_id;

        if (!$supplierId) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $status = $request->query('status', 'pending');

        $lines = PcfRequestLine::whereHas('pcfRequest', fn($q) => $q->where('supplier_id', $supplierId))
            ->when($status, fn($q) => $q->where('status', $status))
            ->with('pcfRequest')
            ->orderBy('created_at')
            ->get()
            ->map(fn($l) => [
                'id'            => $l->id,
                'material_name' => $l->material_name,
                'hs_code'       => $l->hs_code,
                'due_date'      => $l->pcfRequest?->due_date?->toDateString(),
                'status'        => $l->status,
            ]);

        return response()->json(['success' => true, 'data' => $lines]);
    }

    public function index(): JsonResponse
    {
        $supplierId = auth()->user()->supplier_id;

        if (!$supplierId) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $requests = PcfRequest::with('lines')
            ->where('supplier_id', $supplierId)
            ->orderBy('due_date')
            ->get()
            ->map(function (PcfRequest $r) {
                $total     = $r->lines->count();
                $submitted = $r->lines->whereIn('status', ['submitted', 'verified'])->count();
                $daysLeft  = Carbon::today()->diffInDays($r->due_date, false);
                return [
                    'id'            => $r->id,
                    'period_start'  => $r->period_start?->toDateString(),
                    'period_end'    => $r->period_end?->toDateString(),
                    'due_date'      => $r->due_date?->toDateString(),
                    'status'        => $r->status,
                    'saq_round_id'  => $r->saq_round_id,
                    'days_left'     => $daysLeft,
                    'progress'      => ['submitted' => $submitted, 'total' => $total],
                    'lines'         => $r->lines->map(fn($l) => [
                        'id'            => $l->id,
                        'bom_line_id'   => $l->bom_line_id,
                        'material_name' => $l->material_name,
                        'hs_code'       => $l->hs_code,
                        'status'        => $l->status,
                        'submitted_at'  => $l->submitted_at?->toDateString(),
                    ])->values(),
                ];
            });

        return response()->json(['success' => true, 'data' => $requests]);
    }

    public function updateLine(Request $request, string $pcfRequestId, string $lineId): JsonResponse
    {
        $supplierId = auth()->user()->supplier_id;

        $pcfRequest = PcfRequest::where('id', $pcfRequestId)
            ->where('supplier_id', $supplierId)
            ->firstOrFail();

        $line = PcfRequestLine::where('id', $lineId)
            ->where('pcf_request_id', $pcfRequest->id)
            ->firstOrFail();

        $validated = $request->validate([
            'declared_value' => ['required', 'numeric', 'min:0'],
            'quantity_unit'  => ['required', 'string', 'max:20'],
        ], [
            'declared_value.required' => '碳排數值為必填',
            'declared_value.min'      => '碳排數值不可為負數',
            'quantity_unit.required'  => '計量單位為必填',
        ]);

        $line->update([
            'status'       => 'submitted',
            'submitted_at' => Carbon::now(),
        ]);

        // 非同步通知 esgchain-ai 建立 PCFRecord
        dispatch(new \App\Jobs\SyncPcfRecordToAi([
            'pcf_request_line_id' => $line->id,
            'bom_line_id'         => $line->bom_line_id,
            'declared_value'      => $validated['declared_value'],
            'quantity_unit'       => $validated['quantity_unit'],
            'supplier_id'         => $supplierId,
        ]));

        return response()->json(['success' => true, 'data' => $line->fresh()]);
    }

    public function submit(string $pcfRequestId): JsonResponse
    {
        $supplierId = auth()->user()->supplier_id;

        $pcfRequest = PcfRequest::with('lines')
            ->where('id', $pcfRequestId)
            ->where('supplier_id', $supplierId)
            ->firstOrFail();

        $pendingCount = $pcfRequest->lines->where('status', 'pending')->count();
        if ($pendingCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "尚有 {$pendingCount} 筆物料未填寫，請填寫完畢後再提交",
            ], 422);
        }

        $pcfRequest->update(['status' => 'submitted']);

        return response()->json(['success' => true, 'data' => $pcfRequest->fresh()]);
    }
}
