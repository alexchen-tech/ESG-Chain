<?php

namespace App\Services\Chemical;

use App\Models\MaterialItem;
use App\Models\MaterialItemChemical;
use App\Models\ChemicalComplianceAlert;
use App\Models\Chemical;

class ChemicalComplianceScanService
{
    // 各法規的關注清單識別碼（與 Chemical.regulated_lists 的 key 對應）
    private const REGULATED_LISTS = ['reach_svhc', 'rohs'];

    public function scanMaterialItem(string $materialItemId): array
    {
        $chemicals = MaterialItemChemical::where('material_item_id', $materialItemId)->get();
        $newAlerts = [];

        foreach ($chemicals as $mic) {
            $chemical = Chemical::where('cas_no', $mic->cas_no)->first();
            if (!$chemical) {
                continue;
            }

            $regulatedLists = $chemical->regulated_lists ?? [];

            foreach (self::REGULATED_LISTS as $list) {
                if (!in_array($list, array_keys($regulatedLists))) {
                    continue;
                }

                // 已存在且未解決的 alert 不重複建立
                $exists = ChemicalComplianceAlert::where('material_item_id', $materialItemId)
                    ->where('material_item_chemical_id', $mic->id)
                    ->where('regulated_list', $list)
                    ->whereIn('status', ['open', 'acknowledged'])
                    ->exists();

                if (!$exists) {
                    $alert = ChemicalComplianceAlert::create([
                        'material_item_id'         => $materialItemId,
                        'material_item_chemical_id'=> $mic->id,
                        'chemical_id'              => $chemical->id,
                        'regulated_list'           => $list,
                        'alert_level'              => $this->determineLevel($list),
                        'status'                   => 'open',
                    ]);
                    $newAlerts[] = $alert;
                }
            }
        }

        return $newAlerts;
    }

    public function acknowledge(string $alertId, string $userId, ?string $notes = null): ChemicalComplianceAlert
    {
        $alert = ChemicalComplianceAlert::findOrFail($alertId);
        $alert->update([
            'status'           => 'acknowledged',
            'acknowledged_at'  => now(),
            'acknowledged_by'  => $userId,
            'notes'            => $notes,
        ]);

        return $alert;
    }

    private function determineLevel(string $list): string
    {
        return $list === 'reach_svhc' ? 'critical' : 'warning';
    }
}
