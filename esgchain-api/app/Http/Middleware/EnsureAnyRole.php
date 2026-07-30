<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 通用角色白名單守衛：`->middleware('role.any:admin,buyer,sustain,comply')`
 * 依 CLAUDE.md RBAC 表限制寫入端點，只允許列出的角色存取。
 */
class EnsureAnyRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()?->hasAnyRole($roles)) {
            return response()->json(['success' => false, 'message' => '無權限'], 403);
        }

        return $next($request);
    }
}
