<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 補充缺少 bom_line_suppliers 的供應商，依產業對應對應的物料群組 BOM lines。
 */
class SupplierBomGapFillSeeder extends Seeder
{
    // supplier_code => [material_group_name, ...]
    private const SUPPLIER_GROUPS = [
        'VLG-001'  => ['成衣縫製服務'],
        'MTM-001'  => ['棉紡原料', '合成纖維原料'],
        'DGF-001'  => ['成衣縫製服務'],
        'GMN-004'  => ['成衣縫製服務'],
        'GMN-005'  => ['成衣縫製服務', '天然纖維輔料'],
        'GMN-006'  => ['成衣縫製服務'],
        'CTN-005'  => ['棉紡原料'],
        'JPS-001'  => ['金屬配件'],
        'PKG-001'  => ['金屬配件'],
        'LOG-001'  => ['成衣縫製服務'],
        'LOG-002'  => ['成衣縫製服務'],
    ];

    public function run(): void
    {
        $now = now()->toDateTimeString();
        $added = 0;

        // 取得各 material_group → 第一條 bom_line id
        $mgLines = DB::table('product_bom_lines')
            ->join('material_groups', 'product_bom_lines.material_group_id', '=', 'material_groups.id')
            ->select('material_groups.name as mg_name', 'product_bom_lines.id as line_id')
            ->get()
            ->groupBy('mg_name')
            ->map(fn($rows) => $rows->pluck('line_id')->take(2)->values());

        $suppliers = DB::table('suppliers')->whereIn('code', array_keys(self::SUPPLIER_GROUPS))->get()->keyBy('code');

        foreach (self::SUPPLIER_GROUPS as $code => $groups) {
            $supplier = $suppliers->get($code);
            if (!$supplier) continue;

            foreach ($groups as $groupName) {
                $lineIds = $mgLines->get($groupName, collect());
                foreach ($lineIds as $lineId) {
                    $exists = DB::table('bom_line_suppliers')
                        ->where('supplier_id', $supplier->id)
                        ->where('bom_line_id', $lineId)
                        ->exists();
                    if ($exists) continue;

                    DB::table('bom_line_suppliers')->insert([
                        'id'          => (string) Str::uuid(),
                        'bom_line_id' => $lineId,
                        'supplier_id' => $supplier->id,
                        'role'        => 'primary',
                        'source'      => 'erp_designated',
                        'sort_order'  => 0,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ]);
                    $added++;
                }
            }
        }

        $this->command->info("✓ 補充 {$added} 筆 bom_line_suppliers 關聯");
    }
}
