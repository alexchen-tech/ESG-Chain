<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 限制僅 admin 角色可存取（依 CLAUDE.md RBAC 表，settings 系列寫入端點為 admin 專屬模組）。
 * 只掛在寫入類路由（store/update/destroy 等），GET 列表/查詢類路由維持現狀不擋。
 */
class EnsureAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->hasAnyRole(['admin'])) {
            return response()->json(['success' => false, 'message' => '無權限'], 403);
        }

        return $next($request);
    }
}
