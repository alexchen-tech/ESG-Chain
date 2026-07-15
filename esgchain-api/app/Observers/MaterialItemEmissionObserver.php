<?php

namespace App\Observers;

use App\Jobs\RecalcPcfForAffectedProductsJob;
use App\Models\MaterialItemEmission;
use App\Models\PcfRequestLine;

class MaterialItemEmissionObserver
{
    /**
     * 碳排記錄建立後：
     * 1. 更新對應 PcfRequestLine（6.3）
     * 2. dispatch PCF 重算 Job（6.1）
     */
    public function created(MaterialItemEmission $emission): void
    {
        // 6.3: 更新 PcfRequestLine 狀態
        $this->updatePcfRequestLines($emission);

        // 6.1: 非同步觸發 PCF 快照重算
        RecalcPcfForAffectedProductsJob::dispatch($emission->material_item_id, $emission->id);
    }

    private function updatePcfRequestLines(MaterialItemEmission $emission): void
    {
        // 找出所有對應此 (material_item_id × supplier) 且 status = pending 的 PcfRequestLine
        $pendingLines = PcfRequestLine::where('material_item_id', $emission->material_item_id)
            ->where('status', 'pending')
            ->whereHas('pcfRequest', fn($q) => $q->where('supplier_id', $emission->supplier_id))
            ->get();

        foreach ($pendingLines as $line) {
            $line->update([
                'status'               => 'submitted',
                'fulfilled_emission_id'=> $emission->id,
                'submitted_at'         => $emission->created_at,
            ]);

            // 更新 PcfRequest 聚合狀態
            $this->updateRequestAggregateStatus($line->pcfRequest);
        }
    }

    private function updateRequestAggregateStatus($pcfRequest): void
    {
        if (!$pcfRequest) return;

        $lines      = $pcfRequest->lines;
        $total      = $lines->count();
        $submitted  = $lines->where('status', 'submitted')->count() + $lines->where('status', 'verified')->count();

        if ($submitted === 0) return;

        $newStatus = $submitted === $total ? 'submitted' : 'partial';
        $pcfRequest->update(['status' => $newStatus]);
    }
}
