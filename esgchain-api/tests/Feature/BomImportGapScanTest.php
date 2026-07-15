<?php

namespace Tests\Feature;

use App\Jobs\PcfEmissionGapScanJob;
use App\Models\BomLineSupplier;
use App\Models\BuyerProduct;
use App\Models\MaterialItem;
use App\Models\PcfRequest;
use App\Models\PcfRequestLine;
use App\Models\ProductBomLine;
use App\Models\Supplier;
use App\Services\Compliance\BomLineImportService;
use App\Services\PCF\PcfEmissionGapScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 8.2 Feature Test: BOM 匯入 → 缺口掃描 → PcfRequest 建立
 */
class BomImportGapScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_bom_import_dispatches_gap_scan_job(): void
    {
        Queue::fake();

        $product  = BuyerProduct::factory()->create(['product_code' => 'TEST-001']);
        $supplier = Supplier::factory()->create(['code' => 'SUP-001']);

        $importService = app(BomLineImportService::class);
        $importService->importFromArray($product, [
            [
                'erp_line_id'           => 'LINE-001',
                'material_name'         => '精梳棉 32S',
                'material_code'         => 'CTN-32S',
                'hs_code'               => '5205.11',
                'primary_supplier_code' => 'SUP-001',
            ],
        ]);

        Queue::assertPushed(PcfEmissionGapScanJob::class, function ($job) use ($product) {
            return $job->getBuyerProductId() === $product->id;
        });
    }

    public function test_gap_scan_creates_pcf_request_for_missing_emission(): void
    {
        $product      = BuyerProduct::factory()->create();
        $supplier     = Supplier::factory()->create();
        $materialItem = MaterialItem::factory()->create(['item_code' => 'CTN-32S']);

        $bomLine = ProductBomLine::factory()->create([
            'buyer_product_id' => $product->id,
            'material_item_id' => $materialItem->id,
        ]);

        BomLineSupplier::factory()->create([
            'bom_line_id' => $bomLine->id,
            'supplier_id' => $supplier->id,
            'role'        => 'primary',
        ]);

        // 執行缺口掃描
        $service = app(PcfEmissionGapScanService::class);
        $result  = $service->scan(buyerProductId: $product->id, triggerSource: 'system_bom_import');

        $this->assertEquals(1, $result['created']);

        // 確認 PcfRequest 與 PcfRequestLine 已建立
        $pcfRequest = PcfRequest::where('supplier_id', $supplier->id)->first();
        $this->assertNotNull($pcfRequest);
        $this->assertEquals('pending', $pcfRequest->status);
        $this->assertEquals('system_bom_import', $pcfRequest->trigger_source);

        $line = PcfRequestLine::where('pcf_request_id', $pcfRequest->id)->first();
        $this->assertNotNull($line);
        $this->assertEquals($materialItem->id, $line->material_item_id);
        $this->assertEquals('pending', $line->status);
    }

    public function test_gap_scan_skips_existing_emission(): void
    {
        $product      = BuyerProduct::factory()->create();
        $supplier     = Supplier::factory()->create();
        $materialItem = MaterialItem::factory()->create();

        $bomLine = ProductBomLine::factory()->create([
            'buyer_product_id' => $product->id,
            'material_item_id' => $materialItem->id,
        ]);

        BomLineSupplier::factory()->create([
            'bom_line_id' => $bomLine->id,
            'supplier_id' => $supplier->id,
            'role'        => 'primary',
        ]);

        // 建立已存在的碳排記錄
        \App\Models\MaterialItemEmission::factory()->create([
            'material_item_id' => $materialItem->id,
            'supplier_id'      => $supplier->id,
        ]);

        $service = app(PcfEmissionGapScanService::class);
        $result  = $service->scan(buyerProductId: $product->id, triggerSource: 'system_bom_import');

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertEquals(0, PcfRequest::count());
    }
}
