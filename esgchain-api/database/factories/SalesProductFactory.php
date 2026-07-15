<?php

namespace Database\Factories;

use App\Models\SalesProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesProduct>
 */
class SalesProductFactory extends Factory
{
    protected $model = SalesProduct::class;

    public function definition(): array
    {
        return [
            'name'    => fake()->words(3, true),
            'hs_code' => fake()->numerify('####.##'),
            'currency' => 'USD',
        ];
    }
}
