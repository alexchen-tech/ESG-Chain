<?php

namespace App\Services\Erp;

use App\Contracts\ErpAdapterInterface;
use App\Models\BomLineSupplier;
use App\Models\MaterialItem;
use App\Models\ProductBomLine;
use App\Models\SalesProduct;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ErpSyncService
{
    // ERP 擁有的欄位，同步時直接覆蓋
    private const ERP_OWNED_SUPPLIER_FIELDS = [
        'erp_code', 'name', 'country', 'industry', 'tier',
    ];

    // ESG-Chain 擁有的欄位，同步時不觸碰
    private const ESG_OWNED_SUPPLIER_FIELDS = [
        'saq_score', 'risk_level', 'onboarding_stage', 'status',
    ];

    private const ERP_OWNED_BOM_FIELDS = [
        'erp_line_id', 'material_name', 'hs_code', 'quantity', 'unit', 'unit_price', 'currency',
    ];

    // ERP 擁有的 MaterialItem 欄位，同步時直接覆蓋；net_weight、pcr_percentage 為 ESG-Chain 擁有，永不在此清單中
    private const ERP_OWNED_MATERIAL_FIELDS = [
        'item_code', 'name', 'hs_code', 'unit',
    ];

    public function __construct(private readonly ErpAdapterInterface $adapter) {}

    public function syncSuppliers(?string $since = null, string $source = 'scheduled'): array
    {
        $rows = $this->adapter->fetchSuppliers($since);
        $synced = 0;

        foreach ($rows as $row) {
            try {
                $existing = Supplier::where('erp_code', $row['erp_code'] ?? null)->first();

                if ($existing) {
                    // 只更新 ERP 擁有欄位，跳過 ESG 欄位
                    $erpData = array_intersect_key($row, array_flip(self::ERP_OWNED_SUPPLIER_FIELDS));
                    $existing->update($erpData);
                } else {
                    Supplier::create(array_intersect_key($row, array_flip(self::ERP_OWNED_SUPPLIER_FIELDS)));
                }
                $synced++;
            } catch (\Throwable $e) {
                Log::error('ErpSyncService::syncSuppliers 失敗', ['row' => $row, 'error' => $e->getMessage()]);
            }
        }

        return ['synced' => $synced, 'total' => count($rows)];
    }

    public function syncMaterials(?string $since = null, string $source = 'scheduled'): array
    {
        $rows = $this->adapter->fetchMaterials($since);
        $synced = 0;

        foreach ($rows as $row) {
            try {
                $erpData = array_filter(
                    array_intersect_key($row, array_flip(self::ERP_OWNED_MATERIAL_FIELDS)),
                    fn($v) => $v !== null,
                );

                MaterialItem::updateOrCreate(
                    ['item_code' => $row['item_code']],
                    $erpData,
                );
                $synced++;
            } catch (\Throwable $e) {
                Log::error('ErpSyncService::syncMaterials 失敗', ['row' => $row, 'error' => $e->getMessage()]);
            }
        }

        return ['synced' => $synced, 'total' => count($rows)];
    }

    public function syncBomLines(?string $since = null, string $source = 'scheduled'): array
    {
        $rows = $this->adapter->fetchBomLines($since);
        $synced = 0;
        $now = Carbon::now();

        foreach ($rows as $row) {
            try {
                DB::transaction(function () use ($row, $source, $now, &$synced) {
                    $product = SalesProduct::where('product_code', $row['product_code'])->firstOrFail();

                    $materialItem = null;
                    if (!empty($row['material_code'])) {
                        $materialItem = MaterialItem::updateOrCreate(
                            ['item_code' => $row['material_code']],
                            array_filter([
                                'name'    => $row['material_name'] ?? null,
                                'hs_code' => $row['hs_code'] ?? null,
                            ], fn($v) => $v !== null),
                        );
                    }

                    $erpData = array_filter(
                        array_intersect_key($row, array_flip(self::ERP_OWNED_BOM_FIELDS)),
                        fn($v) => $v !== null,
                    );

                    $updateFields = array_merge($erpData, [
                        'material_item_id' => $materialItem?->id,
                        'erp_sync_source'   => $source,
                        'erp_synced_at'     => $now,
                    ]);

                    // 保護 manual 標註的物料群組欄位，邏輯與 BomLineImportService 一致
                    $existing = ProductBomLine::where('sales_product_id', $product->id)
                        ->where('erp_line_id', $row['erp_line_id'])
                        ->first();
                    if ($existing && $existing->material_group_source === 'manual') {
                        unset($updateFields['material_group_id'], $updateFields['material_group_source']);
                    }

                    ProductBomLine::updateOrCreate(
                        [
                            'sales_product_id' => $product->id,
                            'erp_line_id'       => $row['erp_line_id'],
                        ],
                        $updateFields,
                    );
                    $synced++;
                });
            } catch (\Throwable $e) {
                Log::error('ErpSyncService::syncBomLines 失敗', ['row' => $row, 'error' => $e->getMessage()]);
            }
        }

        return ['synced' => $synced, 'total' => count($rows)];
    }

    public function syncShipments(?string $since = null, string $source = 'scheduled'): array
    {
        $rows = $this->adapter->fetchShipments($since);
        // Shipment upsert 由 ShipmentService 處理，此處僅做增量觸發
        // TODO: 委派 ShipmentService::upsertFromErp() 當 Shipment module 完善後
        Log::info('ErpSyncService::syncShipments', ['count' => count($rows), 'source' => $source]);

        return ['synced' => 0, 'total' => count($rows)];
    }
}
