<?php

namespace App\Services\Compliance;

use App\Models\ProductBomLine;
use App\Models\SalesProduct;
use Illuminate\Validation\ValidationException;

class ProductBomLineService
{
    /**
     * 確認加入 child_sales_product_id 不會形成循環參照
     * A → B → C → A 應拒絕
     */
    public function assertNoCycle(string $parentId, string $childId): void
    {
        if ($parentId === $childId) {
            throw ValidationException::withMessages([
                'child_sales_product_id' => '不可將自身加為子產品（循環參照）',
            ]);
        }

        $visited = [];
        $queue   = [$childId];

        while (!empty($queue)) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            $children = ProductBomLine::where('sales_product_id', $current)
                ->whereNotNull('child_sales_product_id')
                ->pluck('child_sales_product_id');

            foreach ($children as $grandChild) {
                if ($grandChild === $parentId) {
                    throw ValidationException::withMessages([
                        'child_sales_product_id' => '加入此子產品會形成循環參照，操作已拒絕',
                    ]);
                }
                $queue[] = $grandChild;
            }
        }
    }

    public function create(SalesProduct $product, array $data): ProductBomLine
    {
        if (!empty($data['child_sales_product_id'])) {
            $this->assertNoCycle($product->id, $data['child_sales_product_id']);
        }

        return $product->bomLines()->create($data);
    }
}
