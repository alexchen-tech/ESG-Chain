<?php

namespace App\Services\Disclosure;

use App\Models\Supplier;
use App\Models\SupplierDisclosure;
use App\Models\SupplierGroup;

class SupplierGhgCoverageService
{
    private const SCOPE1_SLUG = 'ghg.scope1_mt_co2e';
    private const SCOPE2_SLUG = 'ghg.scope2_mt_co2e';
    private const SCOPE3_SLUG = 'ghg.scope3_mt_co2e';

    /**
     * 依供應商彙總範疇一/二/三揭露覆蓋度。
     * $periodYear 為 null 時，取每個 scope 欄位「最近一次」有值的填報紀錄。
     *
     * @return array<int, array{
     *   supplier_id: string,
     *   supplier_name: string,
     *   coverage_level: string,
     *   scope1: array{period_year:int, numeric_value:float}|null,
     *   scope2: array{period_year:int, numeric_value:float}|null,
     *   scope3: array{period_year:int, numeric_value:float}|null,
     * }>
     */
    public function coverageList(?int $periodYear = null, ?string $sortBy = null, string $sortDir = 'desc'): array
    {
        $suppliers = Supplier::orderBy('name')->get(['id', 'name', 'group_id']);

        $query = SupplierDisclosure::whereIn('field_slug', [self::SCOPE1_SLUG, self::SCOPE2_SLUG, self::SCOPE3_SLUG])
            ->whereNotNull('numeric_value');

        if ($periodYear !== null) {
            $query->where('period_year', $periodYear);
        }

        $records = $query->orderBy('period_year')->get(['supplier_id', 'field_slug', 'period_year', 'numeric_value']);

        // 每個 supplier_id × field_slug 取最新一筆（未指定 period_year 時代表「最近一次」）
        $latestBySupplierAndSlug = [];
        foreach ($records as $rec) {
            $latestBySupplierAndSlug[$rec->supplier_id][$rec->field_slug] = [
                'period_year'   => $rec->period_year,
                'numeric_value' => (float) $rec->numeric_value,
            ];
        }

        $result = [];
        foreach ($suppliers as $supplier) {
            $slugValues = $latestBySupplierAndSlug[$supplier->id] ?? [];

            $scope1 = $slugValues[self::SCOPE1_SLUG] ?? null;
            $scope2 = $slugValues[self::SCOPE2_SLUG] ?? null;
            $scope3 = $slugValues[self::SCOPE3_SLUG] ?? null;

            $coverageLevel = $this->classify($scope1, $scope2, $scope3);

            $result[] = [
                'supplier_id'    => $supplier->id,
                'supplier_name'  => $supplier->name,
                'group_id'       => $supplier->group_id,
                'coverage_level' => $coverageLevel,
                'scope1'         => $scope1,
                'scope2'         => $scope2,
                'scope3'         => $scope3,
            ];
        }

        if ($sortBy !== null && in_array($sortBy, ['scope1', 'scope2', 'scope3'], true)) {
            $dir = $sortDir === 'asc' ? 1 : -1;
            usort($result, function ($a, $b) use ($sortBy, $dir) {
                $av = $a[$sortBy]['numeric_value'] ?? null;
                $bv = $b[$sortBy]['numeric_value'] ?? null;
                if ($av === null && $bv === null) return 0;
                if ($av === null) return 1;
                if ($bv === null) return -1;
                return ($av <=> $bv) * $dir;
            });
        }

        return $result;
    }

    /**
     * 分頁版本：先在整份資料上排序，再切頁，避免「排序只作用於當頁」的錯誤。
     *
     * @return array{data: array, total: int, current_page: int, per_page: int, last_page: int}
     */
    public function coverageListPaginated(
        ?int $periodYear = null,
        ?string $sortBy = null,
        string $sortDir = 'desc',
        int $page = 1,
        int $perPage = 20
    ): array {
        $all = $this->coverageList($periodYear, $sortBy, $sortDir);

        $total = count($all);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));

        $data = array_slice($all, ($page - 1) * $perPage, $perPage);

        return [
            'data'         => array_values($data),
            'total'        => $total,
            'current_page' => $page,
            'per_page'     => $perPage,
            'last_page'    => $lastPage,
        ];
    }

    /**
     * 依供應商群組彙總覆蓋度分佈，並附上全體（overall）統計。
     *
     * @return array{
     *   groups: array<int, array{group_id:int|string|null, group_name:string, total:int, not_surveyed_count:int, partial_count:int, full_count:int, coverage_rate:float}>,
     *   overall: array{total:int, not_surveyed_count:int, partial_count:int, full_count:int, coverage_rate:float},
     * }
     */
    public function coverageByGroup(?int $periodYear = null): array
    {
        $list = $this->coverageList($periodYear);

        $groupIds = collect($list)->pluck('group_id')->filter()->unique()->values();
        $groupNames = SupplierGroup::whereIn('id', $groupIds)->pluck('name', 'id');

        $buckets = [];
        foreach ($list as $row) {
            $groupId = $row['group_id'];
            $key = $groupId ?? '__ungrouped__';

            if (!isset($buckets[$key])) {
                $buckets[$key] = [
                    'group_id'   => $groupId,
                    'group_name' => $groupId === null ? '未分組' : ($groupNames[$groupId] ?? '未分組'),
                    'total'      => 0,
                    'not_surveyed_count' => 0,
                    'partial_count'      => 0,
                    'full_count'         => 0,
                ];
            }

            $buckets[$key]['total']++;
            $this->incrementByLevel($buckets[$key], $row['coverage_level']);
        }

        $groups = [];
        foreach ($buckets as $bucket) {
            $bucket['coverage_rate'] = $this->coverageRate($bucket);
            $groups[] = $bucket;
        }

        // 依群組名稱排序，未分組排最後
        usort($groups, function ($a, $b) {
            if ($a['group_id'] === null) return 1;
            if ($b['group_id'] === null) return -1;
            return strcmp($a['group_name'], $b['group_name']);
        });

        $overall = [
            'total'              => 0,
            'not_surveyed_count' => 0,
            'partial_count'      => 0,
            'full_count'         => 0,
        ];
        foreach ($list as $row) {
            $overall['total']++;
            $this->incrementByLevel($overall, $row['coverage_level']);
        }
        $overall['coverage_rate'] = $this->coverageRate($overall);

        return [
            'groups'  => $groups,
            'overall' => $overall,
        ];
    }

    /**
     * 依實際存在的 period_year 逐年彙總全體覆蓋率，呈現成長曲線資料。
     *
     * @return array<int, array{period_year:int, total_suppliers:int, not_surveyed_count:int, partial_count:int, full_count:int, coverage_rate:float}>
     */
    public function coverageTrend(): array
    {
        $years = SupplierDisclosure::whereIn('field_slug', [self::SCOPE1_SLUG, self::SCOPE2_SLUG, self::SCOPE3_SLUG])
            ->whereNotNull('numeric_value')
            ->whereNotNull('period_year')
            ->distinct()
            ->orderBy('period_year')
            ->pluck('period_year');

        $trend = [];
        foreach ($years as $year) {
            $list = $this->coverageList((int) $year);

            $stats = [
                'total'              => 0,
                'not_surveyed_count' => 0,
                'partial_count'      => 0,
                'full_count'         => 0,
            ];
            foreach ($list as $row) {
                $stats['total']++;
                $this->incrementByLevel($stats, $row['coverage_level']);
            }

            $trend[] = [
                'period_year'        => (int) $year,
                'total_suppliers'    => $stats['total'],
                'not_surveyed_count' => $stats['not_surveyed_count'],
                'partial_count'      => $stats['partial_count'],
                'full_count'         => $stats['full_count'],
                'coverage_rate'      => $this->coverageRate($stats),
            ];
        }

        return $trend;
    }

    private function incrementByLevel(array &$bucket, string $level): void
    {
        if ($level === '未盤查') {
            $bucket['not_surveyed_count']++;
        } elseif ($level === '含範疇三') {
            $bucket['full_count']++;
        } else {
            $bucket['partial_count']++;
        }
    }

    private function coverageRate(array $bucket): float
    {
        if ($bucket['total'] === 0) {
            return 0.0;
        }

        $covered = $bucket['partial_count'] + $bucket['full_count'];

        return round($covered / $bucket['total'] * 100, 2);
    }

    private function classify(?array $scope1, ?array $scope2, ?array $scope3): string
    {
        if ($scope1 === null && $scope2 === null && $scope3 === null) {
            return '未盤查';
        }

        if ($scope3 !== null && $scope1 !== null && $scope2 !== null) {
            return '含範疇三';
        }

        return '僅範疇一二';
    }
}
