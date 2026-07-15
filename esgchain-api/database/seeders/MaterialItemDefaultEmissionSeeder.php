<?php

namespace Database\Seeders;

use App\Models\MaterialItem;
use App\Models\MaterialItemEmission;
use Illuminate\Database\Seeder;

/**
 * 植入所有原物料的系統預設碳排放係數（kgCO₂e / 單位）。
 * 數值來源：Ecoinvent 3.10 / GHG Protocol Scope 3 / IPCC AR6 業界公開係數，
 * 以 is_estimated=true, source='system_default' 標記，供各廠商後續覆寫。
 */
class MaterialItemDefaultEmissionSeeder extends Seeder
{
    public function run(): void
    {
        // item_code => [emissions_value(kgCO₂e/unit), calculation_method, notes]
        $defaults = [
            // 棉紡原料（KG / YD）
            'RAW-COT-001' => [5.90,  'cradle_to_gate', '精梳棉紗，含農業+紡紗，Ecoinvent 3.10'],
            'RAW-COT-002' => [4.20,  'cradle_to_gate', '有機棉認證，農藥少，碳排較低'],
            'RAW-COT-003' => [3.80,  'cradle_to_gate', '坯布，YD 基準（約 120g/YD 換算）'],
            'RAW-COT-004' => [5.20,  'cradle_to_gate', '棉滌混紡，加權平均棉+聚酯係數'],

            // 合成纖維原料（KG）
            'RAW-SYN-001' => [9.52,  'cradle_to_gate', '原生聚酯 PET 纖維，GHG Protocol'],
            'RAW-SYN-002' => [7.90,  'cradle_to_gate', '尼龍 6，石化製程，Ecoinvent'],
            'RAW-SYN-003' => [3.10,  'cradle_to_gate', 'rPET 再生聚酯，回收製程減排'],
            'RAW-SYN-004' => [17.40, 'cradle_to_gate', '氨綸 Spandex，MDI 合成，高排放'],
            'RAW-SYN-005' => [10.80, 'cradle_to_gate', '腈綸 Acrylic，AN 聚合製程'],

            // 天然纖維輔料（KG / MTR / YD）
            'RAW-NAT-001' => [27.00, 'cradle_to_gate', '美麗諾羊毛，含牧場甲烷排放'],
            'RAW-NAT-002' => [1.70,  'cradle_to_gate', '亞麻，低投入農業，碳封存效益'],
            'RAW-NAT-003' => [0.38,  'cradle_to_gate', '真絲，MTR 基準，含蠶農製程'],
            'RAW-NAT-004' => [3.40,  'cradle_to_gate', 'Lyocell 天絲，溶劑回收閉環製程'],

            // 染料化學品（KG）
            'CHM-DYE-001' => [12.50, 'cradle_to_gate', '活性染料，苯系化學合成'],
            'CHM-DYE-002' => [14.20, 'cradle_to_gate', '分散染料，石化衍生，高排放'],
            'CHM-DYE-003' => [6.80,  'cradle_to_gate', '固色劑，陽離子聚合物'],
            'CHM-DYE-004' => [9.30,  'cradle_to_gate', '矽酮柔軟劑，矽氧烷合成'],
            'CHM-DYE-005' => [8.60,  'cradle_to_gate', '螢光增白劑，二苯乙烯衍生物'],

            // 金屬配件（PCS / SET）—— 換算為每個零件典型重量
            'ACC-MET-001' => [0.012, 'cradle_to_gate', '黃銅拉鍊頭約 3g，銅熔煉 4.0 kgCO₂e/kg'],
            'ACC-MET-002' => [0.008, 'cradle_to_gate', '不銹鋼四合扣約 3.6g，不銹鋼 2.1 kgCO₂e/kg'],
            'ACC-MET-003' => [0.018, 'cradle_to_gate', '鋁合金調節扣約 8g，鋁鑄造 2.3 kgCO₂e/kg'],
            'ACC-MET-004' => [0.003, 'cradle_to_gate', '銅鉚釘約 0.7g，銅 4.0 kgCO₂e/kg'],

            // 成衣縫製服務（PCS）—— 製程電力+燃料，依複雜度估算
            'SVC-CMT-001' => [0.85,  'process_based',  'T恤 CMT，台灣/越南廠電力 0.5 kWh/件 估算'],
            'SVC-CMT-002' => [2.10,  'process_based',  '牛仔褲含水洗製程，用電+蒸汽'],
            'SVC-CMT-003' => [3.40,  'process_based',  '功能性外套，貼合/壓縫製程，能耗較高'],

            // 染整加工服務（KG / YD）
            'SVC-DYE-001' => [4.20,  'process_based',  '染色 KG 基準，含熱能+廢水處理'],
            'SVC-DYE-002' => [0.28,  'process_based',  'Sanforize YD 基準，蒸汽+壓力製程'],
            'SVC-DYE-003' => [0.35,  'process_based',  'DWR 塗層 YD 基準，PFC-free 熱固化'],

            // 木製包材（PCS / CBM）
            'SVC-PKG-001' => [5.20,  'cradle_to_gate', 'EUR 木棧板 ~22kg 松木，鋸材+熱處理'],
            'SVC-PKG-002' => [120.0, 'cradle_to_gate', '客製木箱 CBM 基準，~300kg 木材估算'],
        ];

        $items = MaterialItem::pluck('id', 'item_code');

        foreach ($defaults as $code => [$value, $method, $notes]) {
            $itemId = $items[$code] ?? null;
            if (!$itemId) continue;

            $existing = MaterialItemEmission::where('material_item_id', $itemId)
                ->whereNull('supplier_id')
                ->where('source', 'system_default')
                ->first();

            $attrs = [
                'emissions_value'    => $value,
                'calculation_method' => $method,
                'reported_period'    => '2024',
                'is_estimated'       => true,
                'is_flagged'         => false,
                'reported_at'        => now(),
            ];

            if ($existing) {
                $existing->update($attrs);
            } else {
                // withoutEvents: 避免觸發 Observer 在 Seeder 期間觸發 PCF 重算
                MaterialItemEmission::withoutEvents(function () use ($attrs, $itemId) {
                    MaterialItemEmission::create(array_merge($attrs, [
                        'material_item_id' => $itemId,
                        'supplier_id'      => null,
                        'source'           => 'system_default',
                    ]));
                });
            }
        }

        $this->command->info('植入 ' . count($defaults) . ' 筆原物料系統預設碳排放係數完成。');
    }
}
