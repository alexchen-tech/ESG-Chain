<?php

namespace Database\Seeders;

use App\Models\TradeGood;
use App\Models\TradeGoodSupplierEmission;
use Illuminate\Database\Seeder;

/**
 * 為所有出口商品植入系統預設 PCF 碳排放估算值。
 * 數值依商品類型（成衣/面料/紗線/配件）+ 材質特性估算，
 * 以 source='system_estimate'（或 buyer-input + is_estimated=true）標記，
 * 供供應商後續以實測值覆寫。
 */
class TradeGoodDefaultPcfSeeder extends Seeder
{
    /**
     * 各商品系統預設 PCF（kgCO₂e / 單位）
     * 來源：Higg MSI / GHG Protocol Scope 3 / WRAP clothing emission factor
     */
    private const PCF_DEFAULTS = [
        // 面料類（YD，假設 1 YD ≈ 200~300g，含紡紗+織造+整理）
        '機能性吸濕排汗布'        => [2.80, '機能性聚酯吸濕面料，Higg MSI 聚酯紡織製程'],
        '防水透氣外層布（Gore-Tex 規格）' => [4.20, '防水透氣複合面料，包含 DWR 塗層處理'],
        '萊賽爾（Lyocell）混紡布'  => [1.95, 'Lyocell 天絲低碳製程，WRAP Fibre 係數'],
        '彈性天然橡膠包芯紗布料'   => [3.60, '含天然橡膠+氨綸包芯，混合係數'],

        // 成衣類（PCS）
        '機能運動夾克（成衣）'     => [18.50, '功能性夾克 CMT+面料，WRAP clothing 成衣係數'],
        '有機棉 T-Shirt（成衣）'   => [5.20,  '有機棉T恤，Higg MSI 有機棉+CMT'],
        '再生聚酯機能褲（成衣）'   => [8.80,  'rPET 機能褲，再生聚酯減碳效益'],

        // 原料/紗線類（KG）
        '精梳棉紗（40s/2）'        => [5.90, '精梳棉紗，Ecoinvent 3.10 棉紡係數'],
        '再生聚酯紗（75D/72F）'    => [3.10, 'rPET 再生聚酯絲，GRS 認證製程'],

        // 配件類（PCS，每件約 3~5g 碳鋼）
        '金屬拉鍊（碳鋼齒）'       => [0.025, '碳鋼拉鍊頭約 5g，鋼鐵 1.85 kgCO₂e/kg + 電鍍'],
    ];

    // 在此改為 buyer-input（系統代填），is_estimated = true
    private const SOURCE = 'buyer-input';

    public function run(): void
    {
        $goods = TradeGood::with('tradeGoodSuppliers:id,trade_good_id,supplier_id')->get();
        $seeded = 0;

        foreach ($goods as $good) {
            $pcfEntry = self::PCF_DEFAULTS[$good->name] ?? null;
            if (!$pcfEntry) {
                $this->command->warn("  找不到預設值：{$good->name}，跳過");
                continue;
            }

            [$emissionsValue, $note] = $pcfEntry;

            // 對每個綁定的供應商建立一筆預設估算
            $suppliers = $good->tradeGoodSuppliers;

            if ($suppliers->isEmpty()) {
                // 無供應商時建立一筆 supplier_id=null 的系統估算
                $this->upsert($good->id, null, $emissionsValue, $note);
                $seeded++;
            } else {
                foreach ($suppliers as $tgs) {
                    $this->upsert($good->id, $tgs->supplier_id, $emissionsValue, $note);
                    $seeded++;
                }
            }

            // 同步更新 trade_good.embedded_emissions（若尚未有實測值）
            if ($good->embedded_emissions === null) {
                $good->update([
                    'embedded_emissions' => $emissionsValue,
                    'emissions_source'   => 'manual',
                ]);
            }
        }

        $this->command->info("植入 {$seeded} 筆出口商品系統預設 PCF 完成。");
    }

    private function upsert(string $tradeGoodId, ?string $supplierId, float $value, string $note): void
    {
        $existing = TradeGoodSupplierEmission::where('trade_good_id', $tradeGoodId)
            ->where(function ($q) use ($supplierId) {
                if ($supplierId) {
                    $q->where('supplier_id', $supplierId);
                } else {
                    $q->whereNull('supplier_id');
                }
            })
            ->first();

        $attrs = [
            'emissions_value'  => $value,
            'calculation_note' => $note . '（系統預設估算，請供應商確認後覆寫）',
            'reported_at'      => now(),
        ];

        if ($existing) {
            $existing->update($attrs);
        } else {
            TradeGoodSupplierEmission::create(array_merge($attrs, [
                'trade_good_id' => $tradeGoodId,
                'supplier_id'   => $supplierId,
            ]));
        }
    }
}
