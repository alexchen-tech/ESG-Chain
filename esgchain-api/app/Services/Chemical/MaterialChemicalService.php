<?php

namespace App\Services\Chemical;

use App\Models\MaterialItem;
use App\Models\MaterialItemChemical;
use App\Jobs\ChemicalComplianceScanJob;
use Illuminate\Support\Facades\DB;

class MaterialChemicalService
{
    public function __construct(private ChemicalRegistryService $registry) {}

    public function list(string $materialItemId): array
    {
        return MaterialItemChemical::where('material_item_id', $materialItemId)
            ->with('chemical')
            ->get()
            ->toArray();
    }

    public function create(string $materialItemId, array $data): MaterialItemChemical
    {
        $casNo = $data['cas_no'];

        // 驗證 CAS No. 格式
        if (!preg_match('/^\d{2,7}-\d{2}-\d$/', $casNo)) {
            throw new \InvalidArgumentException("CAS No. 格式無效：{$casNo}");
        }

        $chemical = DB::transaction(function () use ($materialItemId, $casNo, $data) {
            $record = MaterialItemChemical::create([
                'material_item_id'    => $materialItemId,
                'cas_no'             => $casNo,
                'weight_percentage'  => $data['weight_percentage'] ?? null,
                'reporting_threshold'=> $data['reporting_threshold'] ?? null,
                'source'             => $data['source'] ?? 'buyer_input',
            ]);

            ChemicalComplianceScanJob::dispatch($materialItemId);

            return $record;
        });

        return $chemical;
    }

    public function delete(MaterialItemChemical $chemical): void
    {
        $materialItemId = $chemical->material_item_id;
        $chemical->delete();
        ChemicalComplianceScanJob::dispatch($materialItemId);
    }
}
