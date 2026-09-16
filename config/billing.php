<?php

declare(strict_types=1);

return [
    'free' => [
        'price' => 0,
        'features' => [
            'api' => false,
            'webhooks' => false,
            'departments' => false,
            'saved_replies' => false,
            'advanced_analytics' => false,
            'remove_branding' => false,
            'routing' => false,
        ],
        'limits' => [
            'sites' => 1,
            'employees' => 1,
            'clients' => 200,
            'conversations_month' => 300,
            'history_days' => 30,
        ],
    ],
    'pro' => [
        'price' => 399,
        'features' => [
            'api' => true,
            'webhooks' => true,
            'departments' => true,
            'saved_replies' => true,
            'advanced_analytics' => true,
            'remove_branding' => true,
            'routing' => true,
        ],
        'limits' => [
            'sites' => 10,
            'employees' => 25,
            'clients' => 50000,
            'conversations_month' => 50000,
            'history_days' => 365,
        ],
    ],
];
