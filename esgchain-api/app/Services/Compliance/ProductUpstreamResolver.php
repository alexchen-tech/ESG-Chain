<?php

namespace App\Services\Compliance;

use App\Models\MaterialItemSupplier;
use App\Models\ProductionBatch;
use App\Models\SalesProduct;
use App\Models\SupplierComplianceDoc;
use Illuminate\Support\Collection;

/**
 * 從產品的 BOM 明細（product_bom_lines）與其物料的核可供應商清單
 * （material_item_suppliers）衍生「這個產品的上游供應商」，取代直接讀取
 * TradeGoodSupplier（material_group 粒度、需使用者手動登記）。
 *
 * BOM 行已透過「套用物料核可清單」寫入 bom_line_suppliers 者優先採用；
 * 尚未套用者，退回讀取該 BOM 行物料的 material_item_suppliers 核可清單。
 */
class ProductUpstreamResolver
{
    /** 該產品 BOM 上游物料群組要求的必備文件類型聯集 */
    public function materialGroupDocTypes(SalesProduct $product): array
    {
        $product->loadMissing(['bomLines.materialGroup', 'bomLines.materialItem.materialGroup']);

        return $product->bomLines
            ->map(fn ($line) => $line->materialGroup ?? $line->materialItem?->materialGroup)
            ->filter()
            ->flatMap(fn ($group) => $group->required_doc_types ?? [])
            ->unique()
            ->values()
            ->toArray();
    }

    /** 該產品去重後的上游供應商 ID 清單 */
    public function supplierIds(SalesProduct $product): array
    {
        return $this->effectiveSuppliersByLine($product)
            ->flatMap(fn ($entry) => $entry['supplier_ids'])
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * 該批次「實際選定」的供應商 ID 清單：批號在原料溯源（raw_material_origins）
     * 已指定 supplier_id 的 BOM 行，採用該筆實際供應商；尚未指定實際供應商的
     * BOM 行，退回該行的核可供應商清單（跟 supplierIds() 一致），避免那些
     * 物料完全查不到供應商而讓合規檢查誤判成「無適用義務」。
     *
     * 批號→選供應商→合規調查：市場文件合規檢查應以此為準，而非籠統採用
     * 整個產品 BOM 的全部上游供應商。
     */
    public function batchSupplierIds(ProductionBatch $batch, SalesProduct $product): array
    {
        $batch->loadMissing('rawMaterialOrigins');

        $selectedByLine = $batch->rawMaterialOrigins
            ->whereNotNull('supplier_id')
            ->whereNotNull('bom_line_id')
            ->pluck('supplier_id', 'bom_line_id');

        return $this->effectiveSuppliersByLine($product)
            ->map(function ($entry) use ($selectedByLine) {
                $selected = $selectedByLine->get($entry['bom_line_id']);
                return $selected ? [$selected] : $entry['supplier_ids'];
            })
            ->flatten()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * 該批次相關供應商的合規文件，是否有任一份在 $since 之後更新——用來判斷一筆
     * 「已審查」的批次×市場審查結果是否可能已過期（審查是靜態快照，供應商文件
     * 後續變動不會自動讓審查結果失效）。$since 為 null（尚未審查過）一律回傳 false。
     */
    public function hasNewerComplianceDocsSince(ProductionBatch $batch, SalesProduct $product, ?\DateTimeInterface $since): bool
    {
        if (!$since) {
            return false;
        }

        $supplierIds = $this->batchSupplierIds($batch, $product);
        if (empty($supplierIds)) {
            return false;
        }

        return SupplierComplianceDoc::whereIn('supplier_id', $supplierIds)
            ->where('updated_at', '>', $since)
            ->exists();
    }

    /** 供「上游供應商」彙總顯示用：去重後的供應商清單，含物料群組、製程廠區 */
    public function supplierSummaries(SalesProduct $product): Collection
    {
        $byLine = $this->effectiveSuppliersByLine($product);

        $materialItemIds = $byLine->pluck('material_item_id')->filter()->unique()->values();
        $supplierIds = $byLine->flatMap(fn ($e) => $e['supplier_ids'])->unique()->values();

        // 一次查完所有 (material_item_id, supplier_id) 的製程廠區，避免逐筆查詢
        $facilities = MaterialItemSupplier::whereIn('material_item_id', $materialItemIds)
            ->whereIn('supplier_id', $supplierIds)
            ->whereNotNull('supplier_facility_id')
            ->with('supplierFacility:id,name,facility_type,country')
            ->get()
            ->keyBy(fn ($mis) => $mis->material_item_id . '|' . $mis->supplier_id);

        $summaries = [];

        foreach ($byLine as $entry) {
            foreach ($entry['supplier_ids'] as $supplierId) {
                if (!isset($summaries[$supplierId])) {
                    $summaries[$supplierId] = [
                        'supplier_id'    => $supplierId,
                        'material_groups' => [],
                        'required_doc_types' => [],
                        'supplier_facility_name' => null,
                        'facility_type'          => null,
                        'facility_country'       => null,
                    ];
                }

                if ($entry['material_group_name'] && !in_array($entry['material_group_name'], $summaries[$supplierId]['material_groups'], true)) {
                    $summaries[$supplierId]['material_groups'][] = $entry['material_group_name'];
                }

                foreach ($entry['required_doc_types'] as $dt) {
                    if (!in_array($dt, $summaries[$supplierId]['required_doc_types'], true)) {
                        $summaries[$supplierId]['required_doc_types'][] = $dt;
                    }
                }

                if (!$summaries[$supplierId]['supplier_facility_name']) {
                    $facility = $facilities->get($entry['material_item_id'] . '|' . $supplierId);
                    if ($facility?->supplierFacility) {
                        $summaries[$supplierId]['supplier_facility_name'] = $facility->supplierFacility->name;
                        $summaries[$supplierId]['facility_type'] = $facility->supplierFacility->facility_type;
                        $summaries[$supplierId]['facility_country'] = $facility->supplierFacility->country;
                    }
                }
            }
        }

        return collect(array_values($summaries))->map(fn ($s) => [
            ...$s,
            'material_group' => implode('、', $s['material_groups']),
        ]);
    }

    /**
     * 該批次相關的製程類型清單：BOM 涉及的核可供應商 SupplierFacility.facility_type
     * 聯集，每個製程類型附帶候選供應商清單，並合併批次已選定的 BatchProcessFacility
     * 紀錄，標記 confirmed 狀態與目前選定的供應商/廠區。
     * 見 openspec/changes/batch-process-facility-selection/design.md 決策 2、3。
     */
    public function batchProcessTypes(ProductionBatch $batch, SalesProduct $product): Collection
    {
        $byLine = $this->effectiveSuppliersByLine($product);

        $materialItemIds = $byLine->pluck('material_item_id')->filter()->unique()->values();
        $supplierIds = $byLine->flatMap(fn ($e) => $e['supplier_ids'])->unique()->values();

        $facilityLinks = MaterialItemSupplier::whereIn('material_item_id', $materialItemIds)
            ->whereIn('supplier_id', $supplierIds)
            ->whereNotNull('supplier_facility_id')
            ->with(['supplierFacility:id,name,facility_type,country', 'supplier:id,name'])
            ->get();

        // 依 facility_type 分組，聯集出候選供應商清單（去重 supplier_id + supplier_facility_id）
        $candidatesByType = [];
        foreach ($facilityLinks as $link) {
            $facility = $link->supplierFacility;
            if (!$facility || !$facility->facility_type) {
                continue;
            }

            $type = $facility->facility_type;
            $candidatesByType[$type] ??= [];

            $key = $link->supplier_id . '|' . $link->supplier_facility_id;
            if (!isset($candidatesByType[$type][$key])) {
                $candidatesByType[$type][$key] = [
                    'supplier_id'   => $link->supplier_id,
                    'supplier_name' => $link->supplier?->name,
                    'facility_id'   => $link->supplier_facility_id,
                    'facility_name' => $facility->name,
                    'country'       => $facility->country,
                ];
            }
        }

        $batch->loadMissing('processFacilities.supplier:id,name', 'processFacilities.supplierFacility:id,name,country');
        $selectedByType = $batch->processFacilities->keyBy('process_type');

        return collect(array_keys($candidatesByType))
            ->sort()
            ->values()
            ->map(function ($type) use ($candidatesByType, $selectedByType) {
                $selectedRecord = $selectedByType->get($type);

                $selected = null;
                if ($selectedRecord) {
                    $selected = [
                        'id'            => $selectedRecord->id,
                        'supplier_id'   => $selectedRecord->supplier_id,
                        'supplier_name' => $selectedRecord->supplier?->name,
                        'facility_id'   => $selectedRecord->supplier_facility_id,
                        'facility_name' => $selectedRecord->supplierFacility?->name,
                        'country'       => $selectedRecord->supplierFacility?->country,
                    ];
                }

                return [
                    'process_type' => $type,
                    'confirmed'    => (bool) $selectedRecord,
                    'selected'     => $selected,
                    'candidates'   => array_values($candidatesByType[$type]),
                ];
            });
    }

    /**
     * 每筆 BOM 行的「實際生效供應商」：優先 bom_line_suppliers，
     * 該行尚無登記時退回該物料的 material_item_suppliers 核可清單。
     */
    private function effectiveSuppliersByLine(SalesProduct $product): Collection
    {
        $product->loadMissing([
            'bomLines.materialGroup',
            'bomLines.materialItem.materialGroup',
            'bomLines.bomLineSuppliers',
            'bomLines.materialItem.approvedSuppliers',
        ]);

        return $product->bomLines->map(function ($line) {
            $group = $line->materialGroup ?? $line->materialItem?->materialGroup;

            $supplierIds = $line->bomLineSuppliers->isNotEmpty()
                ? $line->bomLineSuppliers->pluck('supplier_id')->filter()->values()->all()
                : ($line->materialItem?->approvedSuppliers->pluck('supplier_id')->filter()->values()->all() ?? []);

            return [
                'bom_line_id'         => $line->id,
                'material_item_id'    => $line->material_item_id,
                'material_group_name' => $group?->name,
                'required_doc_types'  => $group?->required_doc_types ?? [],
                'supplier_ids'        => $supplierIds,
            ];
        });
    }
}
