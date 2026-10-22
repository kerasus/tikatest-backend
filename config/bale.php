<?php

return [
    'token' => env('BALE_BOT_TOKEN'),
    'bot_username' => env('BALE_BOT_USERNAME'),
    'webhook_secret' => env('BALE_WEBHOOK_SECRET'),
    'link_token_ttl_seconds' => (int) env('BALE_LINK_TOKEN_TTL', 300),
    'redis_prefix' => env('BALE_REDIS_PREFIX', 'bale_link_token:'),
];
