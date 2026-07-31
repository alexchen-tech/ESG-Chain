<?php

namespace App\Services\User;

use App\Models\CAP;
use App\Models\SAQ;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserService
{
    // 中心廠內部使用者角色白名單（供應商 Portal 角色 supplier/sup_esg 走 SupplierUserService）
    public const INTERNAL_ROLES = ['admin', 'buyer', 'sustain', 'comply', 'analyst'];

    public function list(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->whereNull('supplier_id')
            ->with('roles')
            ->orderByDesc('created_at');

        if (!empty($filters['role'])) {
            $query->whereHas('roles', fn($q) => $q->where('name', $filters['role']));
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return $query->paginate($perPage, ['*'], 'page', $filters['page'] ?? 1);
    }

    public function create(array $data, string $createdBy): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'] ?? Str::random(16),
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

    public function updateRoles(User $user, array $roles, string $changedBy, ?string $reason = null): User
    {
        $fromRoles = $user->getRoleNames()->all();

        $user->syncRoles($roles);

        $user->roleHistories()->create([
            'from_roles' => $fromRoles,
            'to_roles' => $roles,
            'changed_by' => $changedBy,
        ]);

        return $user->load('roles');
    }

    public function setActive(User $user, bool $active, string $changedBy, ?string $reason = null): array
    {
        $fromStatus = $user->is_active ? 'active' : 'inactive';
        $toStatus = $active ? 'active' : 'inactive';

        $warnings = [];

        if (!$active) {
            $capCount = CAP::where('assigned_to', $user->id)
                ->whereIn('status', ['open', 'in_progress', 'overdue'])
                ->count();
            $saqCount = SAQ::where('reviewed_by_id', $user->id)
                ->whereIn('status', ['under_review', 'review_returned'])
                ->count();

            if ($capCount > 0) {
                $warnings[] = "此使用者名下有 {$capCount} 筆進行中的 CAP 矯正行動";
            }
            if ($saqCount > 0) {
                $warnings[] = "此使用者名下有 {$saqCount} 筆進行中的 SAQ 覆核工作";
            }
        }

        $user->update(['is_active' => $active]);

        $user->statusHistories()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'changed_by' => $changedBy,
        ]);

        return [
            'user' => $user->fresh('roles'),
            'warnings' => $warnings,
        ];
    }

    public function resetPassword(User $user, string $changedBy): string
    {
        $newPassword = Str::random(4) . '-' . Str::random(4) . '-' . random_int(1000, 9999);

        $user->update(['password' => $newPassword]);

        return $newPassword;
    }
}
