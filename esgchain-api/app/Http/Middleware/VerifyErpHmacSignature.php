<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyErpHmacSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $authMode = config('erp.auth_mode', 'hmac');

        if ($authMode === 'hmac') {
            $secret    = config('erp.webhook_secret', '');
            $signature = $request->header('X-ERP-Signature', '');
            $expected  = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

            if (!hash_equals($expected, $signature)) {
                return response()->json(['message' => 'Invalid signature'], 401);
            }
        } else {
            $apiKey   = config('erp.api_key', '');
            $provided = str_replace('Bearer ', '', $request->header('Authorization', ''));

            if (!hash_equals($apiKey, $provided)) {
                return response()->json(['message' => 'Invalid API key'], 401);
            }
        }

        return $next($request);
    }
}
