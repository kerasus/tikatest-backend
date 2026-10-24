<?php

return [
    'token' => env('BALE_BOT_TOKEN'),

    'bot_username' => env('BALE_BOT_USERNAME'),

    'webhook_secret' => env('BALE_WEBHOOK_SECRET'),

    'api_url' => env(
        'BALE_API_URL',
        'https://tapi.bale.ai'
    ),

    'timeout' => (int) env('BALE_TIMEOUT', 10),

    'retry_times' => (int) env('BALE_RETRY_TIMES', 2),

    'retry_sleep_ms' => (int) env(
        'BALE_RETRY_SLEEP_MS',
        300
    ),

    'link_token_ttl_seconds' => (int) env(
        'BALE_LINK_TOKEN_TTL_SECONDS',
        300
    ),

    'redis_prefix' => env(
        'BALE_REDIS_PREFIX',
        'bale:link_token:'
    ),
];
