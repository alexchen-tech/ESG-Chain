<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * esgchain-ai（Celery worker）回呼 esgchain-api 內部端點的 X-Internal-Token 認證。
 * 共用密鑰為 services.ai.internal_token（env AI_INTERNAL_TOKEN），
 * 與 esgchain-ai 端 INTERNAL_SERVICE_TOKEN 為同一組值；hash_equals 防 timing attack。
 */
class VerifyInternalServiceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->header('X-Internal-Token', '');
        $expected = (string) config('services.ai.internal_token', '');

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return response()->json(['success' => false, 'message' => 'Invalid internal token'], 401);
        }

        return $next($request);
    }
}
