<?php

namespace App\Services;

use App\Models\OrganizationUnit;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;

class OrganizationUnitService
{
    public function getAll(): Collection
    {
        return OrganizationUnit::orderBy('depth')->orderBy('sort_order')->get();
    }

    public function getTree(): array
    {
        $all = $this->getAll()->keyBy('id');
        $childrenMap = [];

        foreach ($all as $unit) {
            $childrenMap[$unit->id] = [];
        }

        foreach ($all as $unit) {
            if ($unit->parent_id && $all->has($unit->parent_id)) {
                $childrenMap[$unit->parent_id][] = $unit;
            }
        }

        foreach ($all as $unit) {
            $unit->setAttribute('children_list', $childrenMap[$unit->id]);
        }

        $roots = [];
        foreach ($all as $unit) {
            if (!$unit->parent_id || !$all->has($unit->parent_id)) {
                $roots[] = $unit;
            }
        }

        return $roots;
    }

    public function create(array $data): OrganizationUnit
    {
        $depth = 1;

        if (!empty($data['parent_id'])) {
            $parent = OrganizationUnit::findOrFail($data['parent_id']);
            $depth = $parent->depth + 1;

            if ($depth > 4) {
                $this->abort422('組織層級最多 4 層');
            }
        }

        $data['depth'] = $depth;

        return OrganizationUnit::create($data);
    }

    public function update(OrganizationUnit $unit, array $data): OrganizationUnit
    {
        // parent_id 和 type 建立後不可更改
        unset($data['parent_id'], $data['type'], $data['depth']);

        $unit->update($data);

        return $unit->fresh();
    }

    public function delete(OrganizationUnit $unit): void
    {
        if ($unit->children()->exists()) {
            $this->abort422('請先移除所有子單位');
        }

        $unit->delete();
    }

    private function abort422(string $message): never
    {
        throw new HttpResponseException(
            response()->json(['success' => false, 'message' => $message], 422)
        );
    }
}
