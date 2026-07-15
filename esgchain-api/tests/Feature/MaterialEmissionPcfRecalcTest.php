<?php

namespace Tests\Feature;

use App\Jobs\RecalcPcfForAffectedProductsJob;
use App\Models\BomLineSupplier;
use App\Models\BuyerProduct;
use App\Models\MaterialItem;
use App\Models\MaterialItemEmission;
use App\Models\PcfRequest;
use App\Models\PcfRequestLine;
use App\Models\ProductBomLine;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 8.3 Feature Test: MaterialItemEmission 建立 → PCF 重算 → 新 PcfSnapshot → PcfRequestLine submitted
 */
class MaterialEmissionPcfRecalcTest extends TestCase
{
    use RefreshDatabase;

    public function test_emission_created_dispatches_recalc_job(): void
    {
        Queue::fake();

        $supplier     = Supplier::factory()->create();
        $materialItem = MaterialItem::factory()->create();

        MaterialItemEmission::factory()->create([
            'material_item_id' => $materialItem->id,
            'supplier_id'      => $supplier->id,
            'source'           => 'portal-self',
        ]);

        Queue::assertPushed(RecalcPcfForAffectedProductsJob::class, function ($job) use ($materialItem) {
            return $job->getMaterialItemId() === $materialItem->id;
        });
    }

    public function test_emission_created_updates_pending_pcf_request_line(): void
    {
        $supplier     = Supplier::factory()->create();
        $materialItem = MaterialItem::factory()->create();
        $product      = BuyerProduct::factory()->create();

        $bomLine = ProductBomLine::factory()->create([
            'buyer_product_id' => $product->id,
            'material_item_id' => $materialItem->id,
        ]);

        BomLineSupplier::factory()->create([
            'bom_line_id' => $bomLine->id,
            'supplier_id' => $supplier->id,
            'role'        => 'primary',
        ]);

        // 建立 pending PcfRequest + PcfRequestLine
        $pcfRequest = PcfRequest::factory()->create([
            'supplier_id'    => $supplier->id,
            'status'         => 'pending',
            'trigger_source' => 'system_bom_import',
        ]);

        $pcfLine = PcfRequestLine::factory()->create([
            'pcf_request_id'  => $pcfRequest->id,
            'material_item_id'=> $materialItem->id,
            'status'          => 'pending',
        ]);

        // 供應商填報碳排 → Observer 自動更新
        $emission = MaterialItemEmission::factory()->create([
            'material_item_id' => $materialItem->id,
            'supplier_id'      => $supplier->id,
            'source'           => 'portal-self',
        ]);

        $pcfLine->refresh();
        $this->assertEquals('submitted', $pcfLine->status);
        $this->assertEquals($emission->id, $pcfLine->fulfilled_emission_id);
        $this->assertNotNull($pcfLine->submitted_at);

        $pcfRequest->refresh();
        $this->assertEquals('submitted', $pcfRequest->status);
    }
}
