<?php

namespace App\Services\User;

use App\Models\User;
use App\Models\UserPermissionOverrideHistory;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Validation\ValidationException;

/**
 * 使用者個人權限覆寫（多授予）。
 *
 * 直接沿用 spatie/laravel-permission 原生的 model_has_permissions pivot（givePermissionTo/
 * revokePermissionTo），有效權限 = 角色權限 ∪ 個人直接權限（spatie 原生聯集語意，不支援負向覆寫）。
 * 見 openspec/changes/crud-permission-granularity/design.md Decision 2。
 *
 * `UserPermissionOverrideHistory` 僅作稽核記錄，不是權限判斷的資料來源。
 */
class UserPermissionOverrideService
{
    /**
     * 回傳使用者的角色權限與個人直接權限（分開標示來源）。
     */
    public function listForUser(User $user): array
    {
        $rolePermissions = $user->hasRole('admin')
            ? array_keys(PermissionCatalogSeeder::CATALOG)
            : $user->getPermissionsViaRoles()->pluck('name')->values()->all();

        $directPermissions = $user->hasRole('admin')
            ? []
            : $user->getDirectPermissions()->pluck('name')->values()->all();

        return [
            'role_permissions' => $rolePermissions,
            'direct_permissions' => $directPermissions,
        ];
    }

    public function grantPermission(User $user, string $permission, User $grantedBy): void
    {
        $this->assertNotAdmin($user);
        $this->assertKnownPermission($permission);

        $user->givePermissionTo($permission);

        UserPermissionOverrideHistory::create([
            'user_id' => $user->id,
            'permission' => $permission,
            'action' => 'grant',
            'granted_by' => $grantedBy->email ?? $grantedBy->id,
        ]);
    }

    public function revokePermission(User $user, string $permission, User $grantedBy): void
    {
        $this->assertNotAdmin($user);
        $this->assertKnownPermission($permission);

        $user->revokePermissionTo($permission);

        UserPermissionOverrideHistory::create([
            'user_id' => $user->id,
            'permission' => $permission,
            'action' => 'revoke',
            'granted_by' => $grantedBy->email ?? $grantedBy->id,
        ]);
    }

    private function assertNotAdmin(User $user): void
    {
        if ($user->hasRole('admin')) {
            throw ValidationException::withMessages([
                'user' => 'admin 使用者已固定擁有全部權限，不可個別覆寫',
            ]);
        }
    }

    private function assertKnownPermission(string $permission): void
    {
        if (!array_key_exists($permission, PermissionCatalogSeeder::CATALOG)) {
            throw ValidationException::withMessages([
                'permission' => '權限字串不存在於權限目錄',
            ]);
        }
    }
}
