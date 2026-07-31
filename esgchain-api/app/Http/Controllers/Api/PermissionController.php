<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\User\UserPermissionOverrideService;
use App\Services\User\UserService;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 角色權限管理 API（限 role.admin，見 openspec role-permission-management design.md Decision 5：
 * 角色管理頁面本身用既有機制保護，不透過權限系統管理，避免自我鎖死）。
 */
class PermissionController extends Controller
{
    public function __construct(private readonly UserPermissionOverrideService $overrideService)
    {
    }

    public function index(): JsonResponse
    {
        $grouped = [];
        foreach (PermissionCatalogSeeder::CATALOG as $name => $description) {
            $module = explode('.', $name)[0];
            $grouped[$module][] = ['name' => $name, 'description' => $description];
        }

        return response()->json(['success' => true, 'data' => $grouped]);
    }

    public function rolePermissions(string $role): JsonResponse
    {
        if (!in_array($role, UserService::INTERNAL_ROLES, true)) {
            return response()->json(['success' => false, 'message' => '角色不存在'], 404);
        }

        if ($role === 'admin') {
            return response()->json([
                'success' => true,
                'data' => [
                    'role' => 'admin',
                    'permissions' => array_keys(PermissionCatalogSeeder::CATALOG),
                    'locked' => true,
                ],
            ]);
        }

        $roleModel = Role::where('name', $role)->where('guard_name', 'api')->first();
        $permissions = $roleModel ? $roleModel->permissions()->pluck('name')->all() : [];

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'permissions' => $permissions,
                'locked' => false,
            ],
        ]);
    }

    public function updateRolePermissions(Request $request, string $role): JsonResponse
    {
        if (!in_array($role, UserService::INTERNAL_ROLES, true)) {
            return response()->json(['success' => false, 'message' => '角色不存在'], 404);
        }

        if ($role === 'admin') {
            return response()->json(['success' => false, 'message' => 'admin 角色權限固定，不可調整'], 422);
        }

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', array_keys(PermissionCatalogSeeder::CATALOG))],
        ]);

        $roleModel = Role::firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        $roleModel->syncPermissions($validated['permissions']);

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'permissions' => $roleModel->permissions()->pluck('name')->all(),
            ],
            'message' => '角色權限已更新',
        ]);
    }

    /**
     * 查詢單一使用者的角色權限與個人直接權限（分開標示來源）。
     */
    public function userPermissions(string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        return response()->json([
            'success' => true,
            'data' => array_merge(
                ['user_id' => $user->id, 'is_admin' => $user->hasRole('admin')],
                $this->overrideService->listForUser($user)
            ),
        ]);
    }

    public function grantUserPermission(Request $request, string $userId, string $permission): JsonResponse
    {
        $user = User::findOrFail($userId);

        try {
            $this->overrideService->grantPermission($user, $permission, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->overrideService->listForUser($user),
            'message' => '已授予個人權限',
        ]);
    }

    public function revokeUserPermission(Request $request, string $userId, string $permission): JsonResponse
    {
        $user = User::findOrFail($userId);

        try {
            $this->overrideService->revokePermission($user, $permission, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->overrideService->listForUser($user),
            'message' => '已收回個人權限',
        ]);
    }
}
