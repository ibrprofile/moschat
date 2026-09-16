<?php

return [
    'driver' => env_val('MAIL_DRIVER', 'smtp'),
    'host' => env_val('MAIL_HOST', '127.0.0.1'),
    'port' => (int) env_val('MAIL_PORT', 25),
    'encryption' => env_val('MAIL_ENCRYPTION', null),
    'username' => env_val('MAIL_USERNAME', null),
    'password' => env_val('MAIL_PASSWORD', null),
    'timeout' => (int) env_val('MAIL_TIMEOUT', 15),
    'from' => [
        'address' => env_val('MAIL_FROM', 'noreply@moschat.online'),
        'name' => env_val('MAIL_FROM_NAME', 'MosChat'),
    ],
    'verify_peer' => (bool) env_val('MAIL_VERIFY_PEER', true),
];
