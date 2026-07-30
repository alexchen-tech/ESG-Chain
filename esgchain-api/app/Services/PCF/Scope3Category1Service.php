<?php

namespace App\Services\PCF;

use App\Models\MaterialItem;
use App\Models\PcfSnapshot;
use App\Models\ProductionBatch;
use Illuminate\Support\Collection;

/**
 * 範疇三類別一（採購商品與服務）物料維度彙總。
 *
 * 邏輯：期間內各產品最新一筆 PcfSnapshot 的逐物料排放小計（subtotal）
 * × 該產品在期間內的生產批次數量加總，依 material_item_id/material_group_id 分組。
 *
 * 注意：這是 ESG-Chain 依 BOM 配方量與批次數量的估算值，非實際物料耗用量，
 * 亦非經第三方查證數字。
 */
class Scope3Category1Service
{
    /**
     * 依物料群組/物料項目彙總期間排放量。
     */
    public function materialRollup(string $periodFrom, string $periodTo, int $page = 1, int $perPage = 20): array
    {
        $batchQuantityByProduct = $this->batchQuantityByProduct($periodFrom, $periodTo);

        if ($batchQuantityByProduct->isEmpty()) {
            return [
                'period_from'     => $periodFrom,
                'period_to'       => $periodTo,
                'total_emissions' => 0.0,
                'note'            => '此為系統依 BOM 配方量與批次數量估算，非實際物料耗用量，亦非經第三方查證數字。',
                'groups'          => [],
                'pagination'      => ['current_page' => 1, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1],
            ];
        }

        $latestSnapshots = $this->latestSnapshotsFor($batchQuantityByProduct->keys()->all());

        // material_item_id => ['total_emissions' => float, 'quality_counts' => [...], 'material_name'=>..]
        $materialTotals = [];

        foreach ($batchQuantityByProduct as $salesProductId => $batchQty) {
            $snapshot = $latestSnapshots->get($salesProductId);
            if (!$snapshot) {
                continue;
            }

            foreach ((array) $snapshot->lines as $line) {
                $materialItemId = $line['material_item_id'] ?? null;
                if (!$materialItemId) {
                    // 型態 B 子產品 BOM line，無 material_item_id，排除
                    continue;
                }

                $subtotal = (float) ($line['subtotal'] ?? 0);
                $contribution = $subtotal * (float) $batchQty;

                if (!isset($materialTotals[$materialItemId])) {
                    $materialTotals[$materialItemId] = [
                        'material_item_id' => $materialItemId,
                        'material_name'    => $line['material_name'] ?? null,
                        'total_emissions'  => 0.0,
                        'quality_counts'   => [],
                        'estimated_count'  => 0,
                        'line_count'       => 0,
                    ];
                }

                $materialTotals[$materialItemId]['total_emissions'] += $contribution;
                $materialTotals[$materialItemId]['line_count']++;

                $dq = $line['data_quality'] ?? 'unknown';
                $materialTotals[$materialItemId]['quality_counts'][$dq] =
                    ($materialTotals[$materialItemId]['quality_counts'][$dq] ?? 0) + 1;

                if (!empty($line['is_estimated'])) {
                    $materialTotals[$materialItemId]['estimated_count']++;
                }
            }
        }

        if (empty($materialTotals)) {
            return [
                'period_from'     => $periodFrom,
                'period_to'       => $periodTo,
                'total_emissions' => 0.0,
                'note'            => '此為系統依 BOM 配方量與批次數量估算，非實際物料耗用量，亦非經第三方查證數字。',
                'groups'          => [],
                'pagination'      => ['current_page' => 1, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1],
            ];
        }

        $materialItems = MaterialItem::with('materialGroup')
            ->whereIn('id', array_keys($materialTotals))
            ->get()
            ->keyBy('id');

        $grandTotal = array_sum(array_column($materialTotals, 'total_emissions'));

        $groups = []; // material_group_id => ['group_name'=>, 'materials'=>[], 'total_emissions'=>]

        foreach ($materialTotals as $materialItemId => $data) {
            $materialItem = $materialItems->get($materialItemId);
            $groupId   = $materialItem?->material_group_id ?? 'ungrouped';
            $groupName = $materialItem?->materialGroup?->name ?? '未分組';

            if (!isset($groups[$groupId])) {
                $groups[$groupId] = [
                    'material_group_id'   => $materialItem?->material_group_id,
                    'material_group_name' => $groupName,
                    'total_emissions'     => 0.0,
                    'materials'           => [],
                ];
            }

            $qualityCounts = $data['quality_counts'];
            $totalLines    = max(1, $data['line_count']);
            $qualityBreakdown = collect($qualityCounts)->map(function ($count) use ($totalLines) {
                return round($count / $totalLines * 100, 1);
            });

            $percentage = $grandTotal > 0 ? round($data['total_emissions'] / $grandTotal * 100, 2) : 0.0;

            $groups[$groupId]['materials'][] = [
                'material_item_id'      => $materialItemId,
                'material_name'         => $materialItem?->name ?? $data['material_name'],
                'total_emissions'       => round($data['total_emissions'], 4),
                'percentage'            => $percentage,
                'data_quality_counts'   => $qualityCounts,
                'data_quality_breakdown' => $qualityBreakdown,
                'estimated_count'       => $data['estimated_count'],
                'line_count'            => $data['line_count'],
            ];

            $groups[$groupId]['total_emissions'] += $data['total_emissions'];
        }

        foreach ($groups as &$group) {
            $group['total_emissions'] = round($group['total_emissions'], 4);
            usort($group['materials'], fn ($a, $b) => $b['total_emissions'] <=> $a['total_emissions']);
        }
        unset($group);

        $groups = collect($groups)->sortByDesc('total_emissions')->values()->all();

        // 分頁單位為「物料群組」（非物料項目）：此彙總資料本質是巢狀（群組→物料項目），
        // 攤平成一維物料清單會打散群組脈絡；群組數量目前遠低於分頁大小，仍在此提供
        // page/per_page 參數以符合分頁規範，避免資料量成長後一次性回傳全部群組。
        $total = count($groups);
        $perPage = max(1, $perPage);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $pagedGroups = array_slice($groups, ($page - 1) * $perPage, $perPage);

        return [
            'period_from'     => $periodFrom,
            'period_to'       => $periodTo,
            'total_emissions' => round($grandTotal, 4),
            'note'            => '此為系統依 BOM 配方量與批次數量估算，非實際物料耗用量，亦非經第三方查證數字。',
            'groups'          => array_values($pagedGroups),
            'pagination'      => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'last_page'    => $lastPage,
            ],
        ];
    }

    /**
     * 下鑽：某物料在期間內被哪些產品/批次/供應商使用，各自貢獻量。
     */
    public function materialDrill(string $materialItemId, string $periodFrom, string $periodTo): array
    {
        $batchQuantityByProduct = $this->batchQuantityByProduct($periodFrom, $periodTo);

        if ($batchQuantityByProduct->isEmpty()) {
            return [
                'material_item_id' => $materialItemId,
                'period_from'      => $periodFrom,
                'period_to'        => $periodTo,
                'total_emissions'  => 0.0,
                'products'         => [],
            ];
        }

        $latestSnapshots = $this->latestSnapshotsFor($batchQuantityByProduct->keys()->all());

        $batches = ProductionBatch::with(['salesProduct', 'supplier'])
            ->whereIn('sales_product_id', $batchQuantityByProduct->keys()->all())
            ->whereBetween('production_date', [$periodFrom, $periodTo])
            ->get()
            ->groupBy('sales_product_id');

        $products = [];
        $grandTotal = 0.0;

        foreach ($batchQuantityByProduct as $salesProductId => $batchQty) {
            $snapshot = $latestSnapshots->get($salesProductId);
            if (!$snapshot) {
                continue;
            }

            $line = collect((array) $snapshot->lines)
                ->first(fn ($l) => ($l['material_item_id'] ?? null) === $materialItemId);

            if (!$line) {
                continue;
            }

            $subtotal = (float) ($line['subtotal'] ?? 0);
            $productBatches = $batches->get($salesProductId, collect());

            $batchList = $productBatches->map(function (ProductionBatch $batch) use ($subtotal) {
                $contribution = $subtotal * (float) $batch->quantity;

                return [
                    'batch_id'         => $batch->id,
                    'erp_batch_no'     => $batch->erp_batch_no,
                    'production_date'  => optional($batch->production_date)->format('Y-m-d'),
                    'quantity'         => (float) $batch->quantity,
                    'unit'             => $batch->unit,
                    'contribution'     => round($contribution, 4),
                    'supplier_id'      => $batch->supplier_id,
                    'supplier_name'    => $batch->supplier?->name,
                ];
            })->values()->all();

            $productContribution = $subtotal * (float) $batchQty;
            $grandTotal += $productContribution;

            $products[] = [
                'sales_product_id'   => $salesProductId,
                'product_name'       => $snapshot->salesProduct?->name,
                'product_code'       => $snapshot->salesProduct?->product_code,
                'subtotal_per_unit'  => $subtotal,
                'batch_quantity'     => (float) $batchQty,
                'contribution'       => round($productContribution, 4),
                'material_supplier_name' => $line['supplier_name'] ?? null,
                'data_quality'       => $line['data_quality'] ?? null,
                'is_estimated'       => (bool) ($line['is_estimated'] ?? false),
                'batches'            => $batchList,
            ];
        }

        usort($products, fn ($a, $b) => $b['contribution'] <=> $a['contribution']);

        $materialItem = MaterialItem::with('materialGroup')->find($materialItemId);

        return [
            'material_item_id'    => $materialItemId,
            'material_name'       => $materialItem?->name,
            'material_group_name' => $materialItem?->materialGroup?->name,
            'period_from'         => $periodFrom,
            'period_to'           => $periodTo,
            'total_emissions'     => round($grandTotal, 4),
            'products'            => $products,
        ];
    }

    /**
     * 期間內各產品的批次數量加總，依 sales_product_id 分組。
     *
     * @return Collection<string, float> sales_product_id => 加總數量
     */
    private function batchQuantityByProduct(string $periodFrom, string $periodTo): Collection
    {
        return ProductionBatch::query()
            ->whereBetween('production_date', [$periodFrom, $periodTo])
            ->selectRaw('sales_product_id, SUM(quantity) as total_quantity')
            ->groupBy('sales_product_id')
            ->get()
            ->pluck('total_quantity', 'sales_product_id')
            ->map(fn ($v) => (float) $v);
    }

    /**
     * 給定產品 id 清單，取各產品最新一筆 PcfSnapshot（依 snapshot_at/created_at 排序）。
     *
     * @param array<string> $salesProductIds
     * @return Collection<string, PcfSnapshot> sales_product_id => 最新快照
     */
    private function latestSnapshotsFor(array $salesProductIds): Collection
    {
        return PcfSnapshot::with('salesProduct')
            ->whereIn('sales_product_id', $salesProductIds)
            ->orderByDesc('snapshot_at')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('sales_product_id')
            ->map(fn ($group) => $group->first());
    }
}
