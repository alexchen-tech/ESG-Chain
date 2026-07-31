<?php

namespace App\Services\User;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class SupplierUserService
{
    public const SUPPLIER_ROLES = ['supplier', 'sup_esg'];

    public function listBySupplier(Supplier $supplier): Collection
    {
        return $supplier->users()->with('roles')->orderByDesc('created_at')->get();
    }

    public function invite(Supplier $supplier, array $data, string $createdBy): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'] ?? \Illuminate\Support\Str::random(16),
            'supplier_id' => $supplier->id,
            'is_active' => true,
        ]);

        $user->assignRole($data['role']);

        $user->roleHistories()->create([
            'from_roles' => [],
            'to_roles' => [$data['role']],
            'changed_by' => $createdBy,
        ]);

        return $user->load('roles');
    }
}
