<?php

return [
    'secret' => env('JWT_SECRET'),
    'keys' => [
        'public' => env('JWT_PUBLIC_KEY'),
        'private' => env('JWT_PRIVATE_KEY'),
        'passphrase' => env('JWT_PASSPHRASE'),
    ],
    'ttl' => env('JWT_ACCESS_TOKEN_TTL', 3600) / 60, // jwt-auth 用分鐘
    'refresh_ttl' => env('JWT_REFRESH_TOKEN_TTL', 604800) / 60,
    'algo' => 'RS256',
    'required_claims' => ['iss', 'iat', 'exp', 'nbf', 'sub', 'jti'],
    'blacklist_enabled' => true,
    'blacklist_grace_period' => 0,
    // 名稱易誤導：這個開關實際控制的是「decode() 遇到已拉黑 token 時要不要丟例外」，
    // 不是單純的例外顯示與否。設 false 等於整個 blacklist 機制形同虛設（登出後 token 仍可正常使用），
    // 這裡明確設為 true，登出/黑名單才會真的擋下已失效的 token。
    'show_black_list_exception' => true,
    'decrypt_cookies' => false,
    'providers' => [
        'jwt' => PHPOpenSourceSaver\JWTAuth\Providers\JWT\Lcobucci::class,
        'auth' => PHPOpenSourceSaver\JWTAuth\Providers\Auth\Illuminate::class,
        'storage' => PHPOpenSourceSaver\JWTAuth\Providers\Storage\Illuminate::class,
    ],
];
