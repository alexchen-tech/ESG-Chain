<?php

namespace Database\Factories;

use App\Models\MaterialGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialGroup>
 */
class MaterialGroupFactory extends Factory
{
    protected $model = MaterialGroup::class;

    public function definition(): array
    {
        return [
            'name'                => fake()->words(2, true),
            'group_type'          => 'material',
            'hs_code_prefixes'    => ['72'],
            'required_doc_types'  => ['SDS'],
            'is_system'           => false,
        ];
    }
}
