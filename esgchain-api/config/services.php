<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // esgchain-ai（FastAPI）服務位址，供 Scope3PushJob 等內部呼叫使用
    'ai' => [
        'url'            => env('AI_SERVICE_URL', 'http://localhost:8000'),
        'timeout'        => env('AI_SERVICE_TIMEOUT', 120),
        // 呼叫 esgchain-ai 純內部（無使用者 JWT）端點時帶的共用密鑰，需與 esgchain-ai
        // 的 INTERNAL_SERVICE_TOKEN 一致（見 core/security.py::verify_internal_service）
        'internal_token' => env('AI_INTERNAL_TOKEN'),
    ],

];
