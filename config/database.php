<?php

declare(strict_types=1);

return [
    'host' => env_val('DB_HOST', '127.0.0.1'),
    'port' => env_val('DB_PORT', '3306'),
    'database' => env_val('DB_DATABASE', 'moschat'),
    'username' => env_val('DB_USERNAME', 'root'),
    'password' => env_val('DB_PASSWORD', ''),
];
