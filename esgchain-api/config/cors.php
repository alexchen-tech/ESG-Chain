<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    // 本機開發環境同時存在多個合法來源：直接打 Vite dev server（5173）、
    // 走 nginx 的中心廠入口（8090）、走 nginx 的供應商 Portal 子網域入口
    // （portal.esgchain.local:8090，見 supplier-portal-frontend-build-split）。
    'allowed_origins' => array_values(array_unique(array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
        'http://localhost:8090',
        'http://portal.esgchain.local:8090',
    ]))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => true,
];
