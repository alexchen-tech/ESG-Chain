<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * 對外批次資料包 API 的 X-Api-Key 認證。
 * 金鑰存 system_settings.export_api_key（可換發）；hash_equals 防 timing attack。
 */
class VerifyExportApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->header('X-Api-Key', '');
        $expected = (string) DB::table('system_settings')->where('key', 'export_api_key')->value('value');

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return response()->json(['success' => false, 'message' => 'Invalid API key'], 401);
        }

        return $next($request);
    }
}
