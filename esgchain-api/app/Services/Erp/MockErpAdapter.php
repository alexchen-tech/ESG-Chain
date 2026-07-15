<?php

namespace App\Services\Erp;

use App\Contracts\ErpAdapterInterface;
use Illuminate\Support\Facades\Log;

class MockErpAdapter implements ErpAdapterInterface
{
    public function fetchSuppliers(?string $since = null): array
    {
        return [];
    }

    public function fetchMaterials(?string $since = null): array
    {
        return [];
    }

    public function fetchBomLines(?string $since = null, ?string $productCode = null): array
    {
        return [];
    }

    public function fetchShipments(?string $since = null): array
    {
        return [];
    }

    public function pushComplianceTag(string $materialCode, string $regulatedList, string $status): bool
    {
        Log::warning('MockErpAdapter::pushComplianceTag 未實作', [
            'material_code'  => $materialCode,
            'regulated_list' => $regulatedList,
            'status'         => $status,
        ]);

        return false;
    }

    public function lockMaterial(string $materialCode, string $reason): bool
    {
        Log::warning('MockErpAdapter::lockMaterial 未實作', [
            'material_code' => $materialCode,
            'reason'        => $reason,
        ]);

        return false;
    }
}
