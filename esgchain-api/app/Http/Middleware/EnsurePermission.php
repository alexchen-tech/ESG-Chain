<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 權限守衛：`->middleware('permission:module.action')`
 * 比照 EnsureAdminRole/EnsureAnyRole 寫法。admin 角色永遠放行（見 PermissionCatalogSeeder 類別註解，
 * admin 權限不透過 role_has_permissions 管理，避免自我鎖死）。
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user || (!$user->hasRole('admin') && !$user->hasPermissionTo($permission))) {
            return response()->json(['success' => false, 'message' => '無權限'], 403);
        }

        return $next($request);
    }
}
