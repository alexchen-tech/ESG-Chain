<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => 'SUP-' . fake()->unique()->numerify('####'),
            'country_code' => 'TW',
            'tier' => 1,
            'status' => 'active',
            'onboarding_stage' => 'active',
        ];
    }
}
