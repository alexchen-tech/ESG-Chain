<?php

namespace Database\Seeders;

use App\Models\BuyerProduct;
use App\Models\BuyerProductTradeGood;
use App\Models\ProductBomLine;
use App\Models\TradeGood;
use Illuminate\Database\Seeder;

class ExportLinkSeeder extends Seeder
{
    public function run(): void
    {
        $links = [
            // TEX-001 基本棉 T 恤 → GMN-TEE-002 有機棉 T-Shirt（成品出口）
            ['buyer' => 'TEX-001', 'trade' => 'GMN-TEE-002', 'type' => 'finished_good', 'bom_material' => null],
            // TEX-002 機能運動長褲 → GMN-PNT-003 再生聚酯機能褲（成品出口）
            ['buyer' => 'TEX-002', 'trade' => 'GMN-PNT-003', 'type' => 'finished_good', 'bom_material' => null],
            // TEX-007 戶外機能夾克 → GMN-JKT-001 機能運動夾克（成品出口）
            ['buyer' => 'TEX-007', 'trade' => 'GMN-JKT-001', 'type' => 'finished_good', 'bom_material' => null],
            // TEX-007 戶外機能夾克 → FAB-WPB-002 防水透氣外層布（原料出口）
            ['buyer' => 'TEX-007', 'trade' => 'FAB-WPB-002', 'type' => 'component', 'bom_material' => '防水透濕三層壓合布'],
            // TEX-006 快乾機能衫 → FAB-DRI-001 機能性吸濕排汗布（原料出口）
            ['buyer' => 'TEX-006', 'trade' => 'FAB-DRI-001', 'type' => 'component', 'bom_material' => null],
        ];

        foreach ($links as $linkDef) {
            $buyer = BuyerProduct::where('product_code', $linkDef['buyer'])->first();
            $trade = TradeGood::where('product_code', $linkDef['trade'])->first();

            if (!$buyer || !$trade) continue;

            $bomLineId = null;
            if ($linkDef['bom_material']) {
                $bomLine = ProductBomLine::where('buyer_product_id', $buyer->id)
                    ->where('material_name', 'like', '%' . $linkDef['bom_material'] . '%')
                    ->first();
                $bomLineId = $bomLine?->id;
            }

            BuyerProductTradeGood::firstOrCreate(
                ['buyer_product_id' => $buyer->id, 'trade_good_id' => $trade->id],
                ['relation_type' => $linkDef['type'], 'bom_line_id' => $bomLineId]
            );
        }
    }
}
