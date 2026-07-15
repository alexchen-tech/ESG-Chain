<?php

namespace Database\Seeders;

use App\Models\OrganizationUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrganizationUnitSeeder extends Seeder
{
    public function run(): void
    {
        if (OrganizationUnit::exists()) {
            return;
        }

        OrganizationUnit::create([
            'id'           => Str::uuid(),
            'name'         => 'ESGChain',
            'code'         => 'HQ',
            'type'         => 'headquarters',
            'parent_id'    => null,
            'country_code' => 'TW',
            'depth'        => 1,
            'sort_order'   => 0,
            'is_active'    => true,
        ]);
    }
}
