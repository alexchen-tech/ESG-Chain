<?php

namespace Tests\Feature;

use App\Models\MaterialItem;
use App\Models\ProductBomLine;
use App\Models\SalesProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bom-material-data-consistency: 手動建立/更新 BomLine 時，若提供 material_item_id，
 * 系統應自動以物料主檔的 name/hs_code 回填快照欄位，避免顯示與主檔不同步。
 */
class ProductBomLineMaterialItemBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_backfills_snapshot_from_material_item(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $product = SalesProduct::factory()->create();
        $materialItem = MaterialItem::factory()->create([
            'name'    => '鋼板 A',
            'hs_code' => '7208.10',
        ]);

        $response = $this->postJson("/api/v1/sales-products/{$product->id}/bom-lines", [
            'material_name'    => '使用者輸入的舊名稱',
            'hs_code'          => '0000.00',
            'material_item_id' => $materialItem->id,
            'bom_line_type'    => 'material',
        ]);

        $response->assertStatus(201);

        $line = ProductBomLine::where('sales_product_id', $product->id)->first();
        $this->assertNotNull($line);
        $this->assertSame('鋼板 A', $line->material_name);
        $this->assertSame('7208.10', $line->hs_code);
    }

    public function test_store_without_material_item_id_keeps_provided_values(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $product = SalesProduct::factory()->create();

        $response = $this->postJson("/api/v1/sales-products/{$product->id}/bom-lines", [
            'material_name' => '自訂服務項目',
            'bom_line_type' => 'service',
        ]);

        $response->assertStatus(201);

        $line = ProductBomLine::where('sales_product_id', $product->id)->first();
        $this->assertSame('自訂服務項目', $line->material_name);
        $this->assertNull($line->material_item_id);
    }

    public function test_update_backfills_snapshot_when_material_item_id_changes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'api');

        $product = SalesProduct::factory()->create();
        $line    = ProductBomLine::factory()->create([
            'sales_product_id' => $product->id,
            'material_name'    => '舊名稱',
        ]);
        $materialItem = MaterialItem::factory()->create([
            'name'    => '鋁捲 B',
            'hs_code' => '7606.12',
        ]);

        $response = $this->patchJson("/api/v1/sales-products/{$product->id}/bom-lines/{$line->id}", [
            'material_item_id' => $materialItem->id,
        ]);

        $response->assertStatus(200);

        $line->refresh();
        $this->assertSame('鋁捲 B', $line->material_name);
        $this->assertSame('7606.12', $line->hs_code);
    }
}
