<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 供應商角色（supplier / sup_esg）API 存取範圍白名單。
 *
 * 背景：routes/api.php 所有業務路由過去共用同一組 auth:api middleware，
 * 只要 JWT 合法，供應商角色即可直接呼叫任何中心廠專用 API（繞過前端 UI）。
 * 此 middleware 在 auth:api 之後掛載，只處理「供應商 vs 非供應商」這條邊界，
 * 不處理中心廠內部角色（buyer/sustain/comply/analyst）之間的權限區分。
 *
 * 非供應商角色（含未登入、無角色）一律放行，交由下游 controller / 既有權限機制處理。
 */
class EnsureSupplierPortalScope
{
    /**
     * 供應商角色可存取的路由白名單，格式為 "METHOD api/v1/xxx"。
     * 字串需與 $request->route()->uri() 回傳格式完全一致（含 api/v1 前綴，
     * apiResource 產生的路由 method 會是 "PUT|PATCH" 這種組合字串）。
     */
    private const ALLOWED_ROUTES = [
        'POST auth/login',
        'POST auth/refresh',
        'GET|HEAD auth/me',
        'POST auth/logout',

        'GET|HEAD portal/procurement-requirements',
        'GET|HEAD portal/trade-goods',
        'POST portal/trade-goods/{tradeGood}/emissions',
        'GET|HEAD portal/material-emissions',
        'POST portal/material-emissions',
        'GET|HEAD portal/material-items',
        'GET|HEAD portal/pcf-request-lines',
        'GET|HEAD portal/pcf-requests',
        'PUT portal/pcf-requests/{pcfRequestId}/lines/{lineId}',
        'POST portal/pcf-requests/{pcfRequestId}/submit',
        'GET|HEAD portal/facilities',
        'POST portal/facilities/{facility}/activity-reports',
        'POST portal/facilities/{facility}/activity-reports/{report}/submit',
        'GET|HEAD portal/disclosures',
        'POST portal/disclosures',
        'GET|HEAD portal/caps',
        'GET|HEAD portal/caps/{cap}',
        'POST portal/caps/{cap}/update',
        'GET|HEAD portal/notifications',
        'GET|HEAD portal/notifications/unread-count',
        'POST portal/notifications/mark-read',
        'POST portal/notifications/{id}/mark-read',

        'POST caps/{cap}/attachments',
        'GET|HEAD cap-attachments/{attachment}/download',
        'DELETE cap-attachments/{attachment}',

        'GET|HEAD suppliers/{supplier}/compliance-docs',
        'POST suppliers/{supplier}/compliance-docs',
        'GET|HEAD compliance-docs/{complianceDoc}/download',

        'PUT suppliers/{supplier}/profile',

        'GET|HEAD questionnaires',
        'GET|HEAD questionnaires/{questionnaire}',
        'PUT questionnaires/{questionnaire}',
        'POST questionnaires/{questionnaire}/submit',
        'POST questionnaires/{questionnaire}/dispute',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : [];
        $isSupplierRole = in_array('supplier', $roles, true) || in_array('sup_esg', $roles, true);

        if (!$isSupplierRole) {
            return $next($request);
        }

        $route = $request->route();
        $uri   = $route ? ltrim($route->uri(), '/') : '';
        // 白名單以無 "api/v1/" 前綴的相對路徑比對（route() uri 一律帶 "api/v1/..." 前綴）
        $uri   = preg_replace('#^api/v1/#', '', $uri);

        // 直接比對完整 "METHOD uri" 字串（method 部分容忍 GET / GET|HEAD 兩種寫法）
        $methodCandidates = array_unique(array_filter([
            $request->method(),
            implode('|', $route->methods()),
            implode('|', array_diff($route->methods(), ['HEAD'])),
        ]));

        foreach ($methodCandidates as $method) {
            if (in_array("{$method} {$uri}", self::ALLOWED_ROUTES, true)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => '無權限存取此資源',
        ], 403);
    }
}
