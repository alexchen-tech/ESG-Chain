<?php

namespace App\Services\OrganizationUnit;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrganizationUnitScopeService
{
    /**
     * 以 $user->organization_unit_id 為根，用 MySQL 8.4 原生 WITH RECURSIVE CTE
     * 撈出自己＋所有子孫組織單位 id。
     *
     * $user->organization_unit_id 為 null（如未指派特定部門的 admin）代表全域可視，
     * 不應呼叫本方法計算子樹——呼叫端（Supplier::scopeVisibleTo）須自行判斷並跳過過濾。
     *
     * @return array<int, string> 可視組織單位 id 清單（含根節點自己）
     */
    public function visibleUnitIds(User $user): array
    {
        $rootId = $user->organization_unit_id;

        if ($rootId === null) {
            // 呼叫端理應不會在 null 時呼叫本方法；防禦性回傳空陣列而非拋例外，避免誤用時意外查出全部資料。
            return [];
        }

        $rows = DB::select(
            <<<'SQL'
            WITH RECURSIVE visible_units AS (
                SELECT id FROM organization_units WHERE id = ?
                UNION ALL
                SELECT ou.id
                FROM organization_units ou
                INNER JOIN visible_units vu ON ou.parent_id = vu.id
            )
            SELECT id FROM visible_units
            SQL,
            [$rootId]
        );

        return array_map(fn ($row) => $row->id, $rows);
    }
}
