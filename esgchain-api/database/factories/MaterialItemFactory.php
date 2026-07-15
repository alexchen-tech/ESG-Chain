<?php

namespace Database\Factories;

use App\Models\MaterialItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialItem>
 */
class MaterialItemFactory extends Factory
{
    protected $model = MaterialItem::class;

    public function definition(): array
    {
        return [
            'item_code' => 'MAT-' . fake()->unique()->numerify('####'),
            'name'      => fake()->words(2, true),
            'hs_code'   => fake()->numerify('####.##'),
            'unit'      => 'kg',
            'is_active' => true,
        ];
    }
}
