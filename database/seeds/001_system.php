<?php

declare(strict_types=1);

use MosChat\Core\Database;

$pdo = Database::connection();

$permissions = [
    ['inbox.read', 'Просмотр Inbox'],
    ['inbox.write', 'Ответы и управление диалогами'],
    ['clients.read', 'Просмотр CRM'],
    ['clients.write', 'Редактирование клиентов'],
    ['visitors.read', 'Просмотр посетителей'],
    ['visitors.write', 'Начать чат с посетителем'],
    ['analytics.read', 'Аналитика'],
    ['employees.read', 'Просмотр сотрудников'],
    ['employees.write', 'Управление сотрудниками'],
    ['departments.read', 'Просмотр отделов'],
    ['departments.write', 'Управление отделами'],
    ['settings.manage', 'Настройки компании'],
    ['api.manage', 'API и webhooks'],
    ['billing.manage', 'Биллинг'],
    ['audit.read', 'Журнал аудита'],
];

foreach ($permissions as [$key, $desc]) {
    $exists = Database::fetch('SELECT id FROM permissions WHERE `key` = ?', [$key]);
    if (!$exists) {
        Database::insert('permissions', ['key' => $key, 'description' => $desc]);
    }
}

$roles = [
    'owner' => 'Владелец',
    'admin' => 'Администратор',
    'supervisor' => 'Супервизор',
    'agent' => 'Оператор',
];

$roleIds = [];
foreach ($roles as $key => $name) {
    $row = Database::fetch('SELECT id FROM roles WHERE company_id IS NULL AND `key` = ?', [$key]);
    if ($row) {
        $roleIds[$key] = (int) $row['id'];
    } else {
        $roleIds[$key] = Database::insert('roles', [
            'company_id' => null,
            'key' => $key,
            'name' => $name,
        ]);
    }
}

$map = [
    'admin' => [
        'inbox.read', 'inbox.write', 'clients.read', 'clients.write', 'visitors.read', 'visitors.write',
        'analytics.read', 'employees.read', 'employees.write', 'departments.read', 'departments.write',
        'settings.manage', 'api.manage', 'billing.manage', 'audit.read',
    ],
    'supervisor' => [
        'inbox.read', 'inbox.write', 'clients.read', 'clients.write', 'visitors.read', 'visitors.write',
        'analytics.read', 'employees.read', 'departments.read',
    ],
    'agent' => [
        'inbox.read', 'inbox.write', 'clients.read', 'clients.write', 'visitors.read', 'visitors.write',
    ],
];

foreach ($map as $roleKey => $permKeys) {
    $roleId = $roleIds[$roleKey];
    foreach ($permKeys as $permKey) {
        $perm = Database::fetch('SELECT id FROM permissions WHERE `key` = ?', [$permKey]);
        if (!$perm) {
            continue;
        }
        $link = Database::fetch(
            'SELECT role_id FROM role_permissions WHERE role_id = ? AND permission_id = ?',
            [$roleId, $perm['id']]
        );
        if (!$link) {
            Database::query(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
                [$roleId, $perm['id']]
            );
        }
    }
}

$billing = require dirname(__DIR__, 2) . '/config/billing.php';
foreach (['free', 'pro'] as $key) {
    $exists = Database::fetch('SELECT id FROM plans WHERE `key` = ?', [$key]);
    $payload = [
        'key' => $key,
        'name' => strtoupper($key) === 'PRO' ? 'Pro' : 'Free',
        'price_monthly' => $billing[$key]['price'],
        'currency' => 'RUB',
        'features' => json_encode($billing[$key]['features'], JSON_UNESCAPED_UNICODE),
        'limits_json' => json_encode($billing[$key]['limits'], JSON_UNESCAPED_UNICODE),
    ];
    if ($exists) {
        Database::update('plans', [
            'name' => $payload['name'],
            'price_monthly' => $payload['price_monthly'],
            'features' => $payload['features'],
            'limits_json' => $payload['limits_json'],
        ], 'id = ?', [(int) $exists['id']]);
    } else {
        Database::insert('plans', $payload);
    }
}
