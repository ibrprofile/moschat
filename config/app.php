<?php

declare(strict_types=1);

return [
    'name' => env_val('APP_NAME', 'MosChat'),
    'env' => env_val('APP_ENV', 'local'),
    'debug' => (bool) env_val('APP_DEBUG', true),
    'url' => env_val('APP_URL', 'https://moschat.online'),
    'key' => env_val('APP_KEY', ''),
];
