<?php

namespace Tests\Unit;

use App\Models\MaterialGroup;
use App\Models\MaterialItem;
use App\Models\ProductBomLine;
use App\Models\SalesProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * bom-material-data-consistency: 法規推算 SHALL 採用 effective 物料群組
 * （優先 materialItem->materialGroup，fallback BomLine 自身 materialGroup）。
 */
class SalesProductRegulationInferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_material_item_group_when_it_differs_from_own_group(): void
    {
        $product = SalesProduct::factory()->create();

        $ownGroup        = MaterialGroup::factory()->create(['required_doc_types' => ['CMRT']]);
        $materialItemGroup = MaterialGroup::factory()->create(['required_doc_types' => ['EUDR_DDS']]);
        $materialItem    = MaterialItem::factory()->create(['material_group_id' => $materialItemGroup->id]);

        ProductBomLine::factory()->create([
            'sales_product_id'  => $product->id,
            'material_item_id'  => $materialItem->id,
            'material_group_id' => $ownGroup->id,
        ]);

        $regulations = $product->syncInferredRegulations();

        $this->assertContains('EUDR', $regulations);
        $this->assertNotContains('CMRT', $regulations);
    }

    public function test_falls_back_to_own_group_when_material_item_has_no_group(): void
    {
        $product  = SalesProduct::factory()->create();
        $ownGroup = MaterialGroup::factory()->create(['required_doc_types' => ['CMRT']]);
        $materialItem = MaterialItem::factory()->create(['material_group_id' => null]);

        ProductBomLine::factory()->create([
            'sales_product_id'  => $product->id,
            'material_item_id'  => $materialItem->id,
            'material_group_id' => $ownGroup->id,
        ]);

        $regulations = $product->syncInferredRegulations();

        $this->assertContains('CMRT', $regulations);
    }

    public function test_no_regulations_when_neither_source_has_a_group(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        $product = SalesProduct::factory()->create();

        ProductBomLine::factory()->create([
            'sales_product_id'  => $product->id,
            'material_group_id' => null,
        ]);

        $regulations = $product->syncInferredRegulations();

        $this->assertEmpty($regulations);
    }
}
