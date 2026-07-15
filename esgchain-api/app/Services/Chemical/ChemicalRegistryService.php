<?php

namespace App\Services\Chemical;

use App\Models\Chemical;

class ChemicalRegistryService
{
    public function lookup(string $casNo): ?Chemical
    {
        return Chemical::where('cas_no', $casNo)->first();
    }

    public function search(string $query): array
    {
        return Chemical::where('cas_no', 'like', "%{$query}%")
            ->orWhere('substance_name', 'like', "%{$query}%")
            ->limit(20)
            ->get()
            ->toArray();
    }
}
