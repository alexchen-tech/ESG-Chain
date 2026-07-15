<?php

return [
    'auth_mode'      => env('ERP_AUTH_MODE', 'hmac'),
    'webhook_secret' => env('ERP_WEBHOOK_SECRET', ''),
    'api_key'        => env('ERP_API_KEY', ''),
];
