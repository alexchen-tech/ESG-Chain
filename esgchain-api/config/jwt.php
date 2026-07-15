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
    'show_black_list_exception' => false,
    'decrypt_cookies' => false,
    'providers' => [
        'jwt' => PHPOpenSourceSaver\JWTAuth\Providers\JWT\Lcobucci::class,
        'auth' => PHPOpenSourceSaver\JWTAuth\Providers\Auth\Illuminate::class,
        'storage' => PHPOpenSourceSaver\JWTAuth\Providers\Storage\Illuminate::class,
    ],
];
