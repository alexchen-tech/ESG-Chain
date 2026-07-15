<?php

namespace App\Services\PCF;

use App\Models\PcfRequest;
use App\Models\PcfRequestLine;
use App\Models\ProductBomLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class PcfRequestService
{
    /**
     * 批次建立 PCF 請求
     * $batches = [
     *   ['supplier_id' => '...', 'bom_line_ids' => [...], 'period_start' => '...', 'period_end' => '...', 'due_date' => '...', 'saq_round_id' => null],
     *   ...
     * ]
     */
    public function batchCreate(array $batches): array
    {
        $created = 0;
        $skipped = [];
        $errors  = [];

        foreach ($batches as $batch) {
            $supplierId  = $batch['supplier_id'];
            $periodStart = $batch['period_start'];
            $bomLineIds  = $batch['bom_line_ids'] ?? [];

            if (empty($bomLineIds)) {
                $errors[] = "supplier_id '{$supplierId}'：bom_line_ids 不可為空";
                continue;
            }

            // 過濾已存在的重複請求
            $existingLineIds = PcfRequestLine::whereHas('pcfRequest', function ($q) use ($supplierId, $periodStart) {
                $q->where('supplier_id', $supplierId)
                  ->where('period_start', $periodStart)
                  ->whereIn('status', ['pending', 'submitted']);
            })->pluck('bom_line_id')->toArray();

            $newLineIds = array_diff($bomLineIds, $existingLineIds);

            if (!empty($existingLineIds)) {
                $skipped[] = [
                    'supplier_id'      => $supplierId,
                    'skipped_bom_lines' => $existingLineIds,
                    'reason'           => '同週期請求已存在',
                ];
            }

            if (empty($newLineIds)) continue;

            $pcfRequest = PcfRequest::create([
                'supplier_id'  => $supplierId,
                'period_start' => $batch['period_start'],
                'period_end'   => $batch['period_end'],
                'due_date'     => $batch['due_date'],
                'status'       => 'pending',
                'saq_round_id' => $batch['saq_round_id'] ?? null,
                'created_by'   => Auth::id(),
            ]);

            foreach ($newLineIds as $bomLineId) {
                $bomLine = ProductBomLine::find($bomLineId);
                if (!$bomLine) {
                    $errors[] = "bom_line_id '{$bomLineId}' 不存在，已略過";
                    continue;
                }

                PcfRequestLine::create([
                    'pcf_request_id' => $pcfRequest->id,
                    'bom_line_id'    => $bomLineId,
                    'material_name'  => $bomLine->effective_material_name ?? $bomLine->material_name,
                    'hs_code'        => $bomLine->effective_hs_code ?? $bomLine->hs_code,
                    'status'         => 'pending',
                ]);
            }

            $created++;
        }

        return compact('created', 'skipped', 'errors');
    }

    /**
     * 查詢 PCF 請求列表（含進度）
     */
    public function list(array $filters = []): array
    {
        $query = PcfRequest::with('supplier', 'lines');

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['period_year'])) {
            $query->whereYear('period_start', $filters['period_year']);
        }
        if (!empty($filters['due_before'])) {
            $query->where('due_date', '<=', $filters['due_before']);
        }

        return $query->orderBy('due_date')->get()->map(function (PcfRequest $r) {
            $total     = $r->lines->count();
            $submitted = $r->lines->whereIn('status', ['submitted', 'verified'])->count();
            return [
                'id'            => $r->id,
                'supplier_id'   => $r->supplier_id,
                'supplier_name' => $r->supplier?->name,
                'period_start'  => $r->period_start?->toDateString(),
                'period_end'    => $r->period_end?->toDateString(),
                'due_date'      => $r->due_date?->toDateString(),
                'status'        => $r->status,
                'saq_round_id'  => $r->saq_round_id,
                'progress'      => ['submitted' => $submitted, 'total' => $total],
                'created_at'    => $r->created_at?->toDateString(),
            ];
        })->values()->toArray();
    }

    /**
     * 將逾期請求更新為 overdue
     */
    public function updateOverdue(): int
    {
        return PcfRequest::where('status', 'pending')
            ->where('due_date', '<', Carbon::today())
            ->update(['status' => 'overdue']);
    }
}
