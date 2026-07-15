<?php

namespace Database\Factories;

use App\Models\ProductBomLine;
use App\Models\SalesProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBomLine>
 */
class ProductBomLineFactory extends Factory
{
    protected $model = ProductBomLine::class;

    public function definition(): array
    {
        return [
            'sales_product_id' => SalesProduct::factory(),
            'material_name'    => fake()->words(2, true),
            'hs_code'          => fake()->numerify('####.##'),
            'bom_line_type'    => 'material',
            'quantity'         => fake()->randomFloat(2, 1, 100),
        ];
    }
}
