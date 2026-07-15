<?php

namespace App\Services\PCF;

use App\Models\BomLineSupplier;
use App\Models\MaterialItemEmission;
use App\Models\PcfRequest;
use App\Models\PcfRequestLine;
use App\Models\ProductBomLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PcfEmissionGapScanService
{
    /**
     * 掃描 (material_item_id × supplier_id) 碳排缺口，建立或更新 PcfRequest/PcfRequestLine
     *
     * @param string|null $salesProductId  null = 掃描所有產品
     * @param string|null $supplierId      null = 掃描所有供應商
     * @param string      $triggerSource   pcf_requests.trigger_source
     */
    public function scan(
        ?string $salesProductId = null,
        ?string $supplierId = null,
        string $triggerSource = 'system_bom_import',
    ): array {
        $created = 0;
        $skipped = 0;

        // 1. 找出所有有 primary supplier 的 BOM 行（含 MaterialItem）
        $query = BomLineSupplier::query()
            ->where('role', 'primary')
            ->whereHas('bomLine', fn($q) => $q->whereNotNull('material_item_id'))
            ->with(['bomLine.materialItem', 'bomLine.salesProduct']);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($salesProductId) {
            $query->whereHas('bomLine', fn($q) => $q->where('sales_product_id', $salesProductId));
        }

        $primaryLinks = $query->get();

        foreach ($primaryLinks as $link) {
            $bomLine      = $link->bomLine;
            $materialItem = $bomLine->materialItem;

            if (!$materialItem) continue;

            $materialItemId = $materialItem->id;
            $linkSupplierId = $link->supplier_id;

            // 2. 檢查是否已有碳排記錄
            $hasEmission = MaterialItemEmission::where('material_item_id', $materialItemId)
                ->where('supplier_id', $linkSupplierId)
                ->exists();

            if ($hasEmission) {
                $skipped++;
                continue;
            }

            // 3. 找或建立對應 PcfRequest（per-supplier）
            $pcfRequest = $this->findOrCreateRequest($linkSupplierId, $triggerSource);

            // 4. 檢查是否已有對應 PcfRequestLine（依 material_item_id 去重）
            $existingLine = PcfRequestLine::where('pcf_request_id', $pcfRequest->id)
                ->where('material_item_id', $materialItemId)
                ->first();

            if ($existingLine) {
                $skipped++;
                continue;
            }

            // 5. 建立 PcfRequestLine
            PcfRequestLine::create([
                'pcf_request_id' => $pcfRequest->id,
                'material_item_id' => $materialItemId,
                'bom_line_id'    => $bomLine->id,
                'material_name'  => $materialItem->name,
                'hs_code'        => $materialItem->hs_code,
                'status'         => 'pending',
            ]);

            $created++;
        }

        Log::info('PcfEmissionGapScanService::scan 完成', [
            'sales_product_id' => $salesProductId,
            'supplier_id'      => $supplierId,
            'trigger_source'   => $triggerSource,
            'created'          => $created,
            'skipped'          => $skipped,
        ]);

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function findOrCreateRequest(string $supplierId, string $triggerSource): PcfRequest
    {
        // 找同供應商的 pending/partial 請求（同週期內合併）
        $existing = PcfRequest::where('supplier_id', $supplierId)
            ->whereIn('status', ['pending', 'partial'])
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        $now = Carbon::now();

        return PcfRequest::create([
            'supplier_id'    => $supplierId,
            'period_start'   => $now->copy()->startOfYear()->toDateString(),
            'period_end'     => $now->copy()->endOfYear()->toDateString(),
            'due_date'       => $now->copy()->addDays(30)->toDateString(),
            'status'         => 'pending',
            'trigger_source' => $triggerSource,
        ]);
    }
}
