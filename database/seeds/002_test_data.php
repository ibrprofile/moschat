<?php

declare(strict_types=1);

use MosChat\Core\Database;

$pdo = Database::connection();

$companyId = 1;
$siteId    = 1;
$ownerId   = 1; // Азамат

$now  = date('Y-m-d H:i:s');
$ago  = fn(int $days, int $hours = 0) => date('Y-m-d H:i:s', strtotime("-{$days} days -{$hours} hours"));

// ──────────────────────────────────────────────
// 1. Дополнительные сотрудники
// ──────────────────────────────────────────────
$agentRoleId = (int) Database::fetch("SELECT id FROM roles WHERE `key`='agent' AND company_id IS NULL")['id'];
$supervisorRoleId = (int) Database::fetch("SELECT id FROM roles WHERE `key`='supervisor' AND company_id IS NULL")['id'];

$agents = [
    ['name' => 'Мария Смирнова',  'email' => 'maria@tesa.local',   'role' => $agentRoleId],
    ['name' => 'Дмитрий Козлов',  'email' => 'dmitry@tesa.local',  'role' => $agentRoleId],
    ['name' => 'Алина Попова',    'email' => 'alina@tesa.local',    'role' => $supervisorRoleId],
];

$agentIds = [$ownerId];
foreach ($agents as $a) {
    $user = Database::fetch('SELECT id FROM users WHERE email = ?', [$a['email']]);
    if (!$user) {
        $uid = Database::insert('users', [
            'email'             => $a['email'],
            'password_hash'     => password_hash('Test1234!', PASSWORD_BCRYPT),
            'name'              => $a['name'],
            'timezone'          => 'Europe/Moscow',
            'locale'            => 'ru',
            'email_verified_at' => $now,
            'status'            => 'active',
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
    } else {
        $uid = (int) $user['id'];
    }
    $agentIds[] = $uid;
    $member = Database::fetch('SELECT id FROM company_members WHERE company_id=? AND user_id=?', [$companyId, $uid]);
    if (!$member) {
        Database::insert('company_members', [
            'company_id' => $companyId,
            'user_id'    => $uid,
            'role_id'    => $a['role'],
            'status'     => 'active',
            'presence'   => 'online',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

// ──────────────────────────────────────────────
// 2. Отделы
// ──────────────────────────────────────────────
$depts = [
    ['name' => 'Продажи',     'color' => '#10B981', 'icon' => 'shopping-cart'],
    ['name' => 'Техподдержка','color' => '#3B82F6', 'icon' => 'wrench'],
    ['name' => 'Бухгалтерия', 'color' => '#F59E0B', 'icon' => 'calculator'],
];
$deptIds = [];
foreach ($depts as $d) {
    $row = Database::fetch('SELECT id FROM departments WHERE company_id=? AND name=?', [$companyId, $d['name']]);
    if (!$row) {
        $did = Database::insert('departments', [
            'company_id'  => $companyId,
            'name'        => $d['name'],
            'color'       => $d['color'],
            'icon'        => $d['icon'],
            'is_default'  => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    } else {
        $did = (int) $row['id'];
    }
    $deptIds[] = $did;
}

// Раскидываем сотрудников по отделам
foreach ($agentIds as $i => $uid) {
    $did = $deptIds[$i % count($deptIds)];
    $exists = Database::fetch('SELECT 1 FROM department_members WHERE department_id=? AND user_id=?', [$did, $uid]);
    if (!$exists) {
        Database::query('INSERT INTO department_members (department_id, user_id) VALUES (?,?)', [$did, $uid]);
    }
}

// ──────────────────────────────────────────────
// 3. Теги
// ──────────────────────────────────────────────
$tagDefs = [
    ['name' => 'Срочно',     'color' => '#EF4444'],
    ['name' => 'VIP',        'color' => '#8B5CF6'],
    ['name' => 'Lead',       'color' => '#3B82F6'],
    ['name' => 'Hot',        'color' => '#F97316'],
    ['name' => 'Support',    'color' => '#10B981'],
    ['name' => 'Повторный',  'color' => '#6366F1'],
];
$tagIds = [];
foreach ($tagDefs as $t) {
    $row = Database::fetch('SELECT id FROM tags WHERE company_id=? AND name=?', [$companyId, $t['name']]);
    if (!$row) {
        $tid = Database::insert('tags', [
            'company_id' => $companyId,
            'name'       => $t['name'],
            'color'      => $t['color'],
            'created_at' => $now,
        ]);
    } else {
        $tid = (int) $row['id'];
    }
    $tagIds[$t['name']] = $tid;
}

// ──────────────────────────────────────────────
// 4. Клиенты
// ──────────────────────────────────────────────
$clientDefs = [
    ['name' => 'Иван Петров',     'email' => 'ivan@example.com',   'phone' => '+7 905 111 2233', 'status' => 'client',   'company' => 'ООО Ромашка',   'source' => 'website', 'tags' => ['VIP', 'Повторный']],
    ['name' => 'Ольга Федорова',  'email' => 'olga@example.com',   'phone' => '+7 916 222 3344', 'status' => 'lead',     'company' => null,             'source' => 'website', 'tags' => ['Lead', 'Срочно']],
    ['name' => 'Сергей Волков',   'email' => 'sergey@example.com', 'phone' => '+7 926 333 4455', 'status' => 'client',   'company' => 'ИП Волков С.А.', 'source' => 'website', 'tags' => ['Hot']],
    ['name' => 'Анна Морозова',   'email' => 'anna@example.com',   'phone' => null,              'status' => 'lead',     'company' => null,             'source' => 'website', 'tags' => ['Lead']],
    ['name' => 'Алексей Соколов', 'email' => 'alexey@example.com', 'phone' => '+7 903 444 5566', 'status' => 'churned', 'company' => 'ПАО Техника',    'source' => 'website', 'tags' => ['Support']],
    ['name' => 'Екатерина Новак', 'email' => 'kate@example.com',   'phone' => '+7 999 555 6677', 'status' => 'client',   'company' => 'ЗАО Новак',      'source' => 'website', 'tags' => ['VIP', 'Hot']],
    ['name' => 'Михаил Зайцев',   'email' => 'misha@example.com',  'phone' => null,              'status' => 'lead',     'company' => null,             'source' => 'website', 'tags' => ['Lead']],
    ['name' => 'Дарья Кузнецова', 'email' => 'dasha@example.com',  'phone' => '+7 912 666 7788', 'status' => 'client',   'company' => 'ИП Кузнецова',   'source' => 'website', 'tags' => ['Повторный']],
];

$clientIds = [];
foreach ($clientDefs as $i => $c) {
    $row = Database::fetch('SELECT id FROM clients WHERE company_id=? AND email=?', [$companyId, $c['email']]);
    if (!$row) {
        $cid = Database::insert('clients', [
            'company_id'          => $companyId,
            'name'                => $c['name'],
            'email'               => $c['email'],
            'phone'               => $c['phone'],
            'company_name'        => $c['company'],
            'status'              => $c['status'],
            'source'              => $c['source'],
            'responsible_user_id' => $agentIds[$i % count($agentIds)],
            'department_id'       => $deptIds[$i % count($deptIds)],
            'first_contact_at'    => $ago(rand(10, 60)),
            'last_contact_at'     => $ago(rand(0, 10)),
            'conversations_count' => rand(1, 8),
            'created_at'          => $ago(rand(10, 60)),
            'updated_at'          => $ago(rand(0, 5)),
        ]);
    } else {
        $cid = (int) $row['id'];
    }
    $clientIds[] = $cid;

    // Теги
    foreach ($c['tags'] as $tName) {
        if (!isset($tagIds[$tName])) continue;
        $exists = Database::fetch('SELECT 1 FROM client_tags WHERE client_id=? AND tag_id=?', [$cid, $tagIds[$tName]]);
        if (!$exists) {
            Database::query('INSERT INTO client_tags (client_id, tag_id) VALUES (?,?)', [$cid, $tagIds[$tName]]);
        }
    }
}

// ──────────────────────────────────────────────
// 5. Воронка и сделки
// ──────────────────────────────────────────────
$pipeline = Database::fetch('SELECT id FROM pipelines WHERE company_id=?', [$companyId]);
if (!$pipeline) {
    $pipeId = Database::insert('pipelines', [
        'company_id' => $companyId,
        'name'       => 'Основная воронка',
        'is_default' => 1,
        'created_at' => $now,
    ]);
} else {
    $pipeId = (int) $pipeline['id'];
}

$stages = [
    ['name' => 'Новый лид',     'key' => 'new',         'position' => 0, 'color' => '#6B7280'],
    ['name' => 'Переговоры',    'key' => 'negotiation', 'position' => 1, 'color' => '#3B82F6'],
    ['name' => 'КП отправлено', 'key' => 'proposal',    'position' => 2, 'color' => '#F59E0B'],
    ['name' => 'Договор',       'key' => 'contract',    'position' => 3, 'color' => '#8B5CF6'],
    ['name' => 'Оплата',        'key' => 'payment',     'position' => 4, 'color' => '#10B981'],
];
$stageIds = [];
foreach ($stages as $s) {
    $row = Database::fetch('SELECT id FROM pipeline_stages WHERE pipeline_id=? AND stage_key=?', [$pipeId, $s['key']]);
    if (!$row) {
        $sid = Database::insert('pipeline_stages', [
            'pipeline_id' => $pipeId,
            'name'        => $s['name'],
            'stage_key'   => $s['key'],
            'position'    => $s['position'],
            'color'       => $s['color'],
        ]);
    } else {
        $sid = (int) $row['id'];
    }
    $stageIds[$s['key']] = $sid;
}

$deals = [
    ['client' => 0, 'title' => 'Лицензия ПО — ООО Ромашка',      'amount' => 85000,  'stage' => 'contract',    'status' => 'open'],
    ['client' => 2, 'title' => 'Интеграция CRM — ИП Волков',      'amount' => 45000,  'stage' => 'negotiation', 'status' => 'open'],
    ['client' => 5, 'title' => 'Корпоративный тариф — ЗАО Новак', 'amount' => 120000, 'stage' => 'payment',     'status' => 'open'],
    ['client' => 1, 'title' => 'Пробный период — Федорова',        'amount' => 0,      'stage' => 'new',         'status' => 'open'],
    ['client' => 4, 'title' => 'Поддержка — ПАО Техника',         'amount' => 30000,  'stage' => 'proposal',    'status' => 'open'],
    ['client' => 7, 'title' => 'Абонемент — ИП Кузнецова',        'amount' => 18000,  'stage' => 'contract',    'status' => 'open'],
];

foreach ($deals as $i => $d) {
    $cid = $clientIds[$d['client']] ?? $clientIds[0];
    $exists = Database::fetch('SELECT id FROM deals WHERE company_id=? AND title=?', [$companyId, $d['title']]);
    if (!$exists) {
        Database::insert('deals', [
            'company_id'          => $companyId,
            'client_id'           => $cid,
            'pipeline_id'         => $pipeId,
            'stage_id'            => $stageIds[$d['stage']],
            'title'               => $d['title'],
            'amount'              => $d['amount'],
            'currency'            => 'RUB',
            'responsible_user_id' => $agentIds[$i % count($agentIds)],
            'status'              => $d['status'],
            'created_at'          => $ago(rand(1, 20)),
            'updated_at'          => $ago(rand(0, 3)),
        ]);
    }
}

// ──────────────────────────────────────────────
// 6. Диалоги и сообщения
// ──────────────────────────────────────────────
$convDefs = [
    [
        'client' => 0, 'status' => 'open', 'assigned' => $ownerId,
        'dept' => $deptIds[0], 'days_ago' => 1,
        'messages' => [
            ['sender_type' => 'visitor', 'body' => 'Здравствуйте! Хотел бы узнать про корпоративный тариф.'],
            ['sender_type' => 'agent',   'body' => 'Добрый день! Конечно, расскажу. Что именно вас интересует?'],
            ['sender_type' => 'visitor', 'body' => 'Нас 15 человек, нужен безлимитный чат и CRM интеграция.'],
            ['sender_type' => 'agent',   'body' => 'Отлично, для команды 15+ человек подойдёт тариф Pro. Отправлю КП на почту.'],
        ],
    ],
    [
        'client' => 1, 'status' => 'new', 'assigned' => null,
        'dept' => $deptIds[1], 'days_ago' => 0,
        'messages' => [
            ['sender_type' => 'visitor', 'body' => 'Добрый день, не могу войти в систему, пишет неверный пароль.'],
            ['sender_type' => 'visitor', 'body' => 'Уже пробовала восстановить — письмо не приходит.'],
        ],
    ],
    [
        'client' => 2, 'status' => 'pending', 'assigned' => $agentIds[1] ?? $ownerId,
        'dept' => $deptIds[0], 'days_ago' => 3,
        'messages' => [
            ['sender_type' => 'visitor', 'body' => 'Хотим подключить ваш сервис к нашей 1С, это возможно?'],
            ['sender_type' => 'agent',   'body' => 'Да, у нас есть REST API. Документацию отправлю сейчас.'],
            ['sender_type' => 'visitor', 'body' => 'Получил, спасибо. Изучим и вернёмся.'],
            ['sender_type' => 'agent',   'body' => 'Хорошо, ждём! Если будут вопросы — пишите.'],
            ['sender_type' => 'visitor', 'body' => 'Ещё вопрос — есть ли webhook на новые сообщения?'],
            ['sender_type' => 'agent',   'body' => 'Есть, поддерживаем webhook на все события. Настраивается в разделе API.'],
        ],
    ],
    [
        'client' => 5, 'status' => 'closed', 'assigned' => $agentIds[2] ?? $ownerId,
        'dept' => $deptIds[2], 'days_ago' => 7,
        'messages' => [
            ['sender_type' => 'visitor', 'body' => 'Здравствуйте, когда выставите счёт за следующий месяц?'],
            ['sender_type' => 'agent',   'body' => 'Счёт выставим 1-го числа, пришлём на вашу почту.'],
            ['sender_type' => 'visitor', 'body' => 'Отлично, спасибо!'],
        ],
    ],
    [
        'client' => 3, 'status' => 'open', 'assigned' => $agentIds[1] ?? $ownerId,
        'dept' => $deptIds[1], 'days_ago' => 0,
        'messages' => [
            ['sender_type' => 'visitor', 'body' => 'Привет! Можно попробовать бесплатно?'],
            ['sender_type' => 'agent',   'body' => 'Конечно! У нас есть 14-дневный триал без карты. Зарегистрируйтесь на сайте.'],
            ['sender_type' => 'visitor', 'body' => 'Супер, уже регистрируюсь.'],
        ],
    ],
    [
        'client' => 6, 'status' => 'new', 'assigned' => null,
        'dept' => null, 'days_ago' => 0,
        'messages' => [
            ['sender_type' => 'visitor', 'body' => 'Есть ли мобильное приложение для операторов?'],
        ],
    ],
];

$convIds = [];
foreach ($convDefs as $i => $cd) {
    $cid = $clientIds[$cd['client']] ?? $clientIds[0];
    $createdAt = $ago($cd['days_ago'], rand(1, 6));
    $lastMsgAt = $ago(0, rand(0, $cd['days_ago'] * 24));

    $conv = Database::fetch(
        'SELECT id FROM conversations WHERE company_id=? AND client_id=? AND status=? LIMIT 1',
        [$companyId, $cid, $cd['status']]
    );
    if (!$conv) {
        $lastMsg = end($cd['messages']);
        $convId = Database::insert('conversations', [
            'company_id'              => $companyId,
            'site_id'                 => $siteId,
            'client_id'               => $cid,
            'visitor_id'              => null,
            'channel'                 => 'website',
            'status'                  => $cd['status'],
            'assigned_user_id'        => $cd['assigned'],
            'assigned_department_id'  => $cd['dept'],
            'last_message_at'         => $lastMsgAt,
            'last_message_preview'    => mb_substr($lastMsg['body'], 0, 100),
            'unread_agent_count'      => $cd['status'] === 'new' ? count($cd['messages']) : 0,
            'unread_visitor_count'    => 0,
            'closed_at'               => $cd['status'] === 'closed' ? $ago($cd['days_ago']) : null,
            'created_at'              => $createdAt,
            'updated_at'              => $lastMsgAt,
        ]);
    } else {
        $convId = (int) $conv['id'];
    }
    $convIds[] = $convId;

    // Сообщения
    $existingMsgs = Database::fetch('SELECT id FROM messages WHERE conversation_id=? LIMIT 1', [$convId]);
    if (!$existingMsgs) {
        $msgTime = strtotime($createdAt);
        foreach ($cd['messages'] as $j => $m) {
            $msgTime += rand(60, 600);
            $msgAt = date('Y-m-d H:i:s', $msgTime);
            Database::insert('messages', [
                'conversation_id' => $convId,
                'company_id'      => $companyId,
                'channel'         => 'website',
                'sender_type'     => $m['sender_type'],
                'sender_id'       => $m['sender_type'] === 'agent' ? $ownerId : $cid,
                'message_type'    => 'text',
                'body'            => $m['body'],
                'status'          => 'read',
                'created_at'      => $msgAt,
                'updated_at'      => $msgAt,
            ]);
        }
    }

    // Теги диалога
    $convTagName = ['VIP', 'Lead', 'Hot', 'Support', 'Срочно'][$i % 5];
    if (isset($tagIds[$convTagName])) {
        $exists = Database::fetch('SELECT 1 FROM conversation_tags WHERE conversation_id=? AND tag_id=?', [$convId, $tagIds[$convTagName]]);
        if (!$exists) {
            Database::query('INSERT INTO conversation_tags (conversation_id, tag_id) VALUES (?,?)', [$convId, $tagIds[$convTagName]]);
        }
    }
}

// ──────────────────────────────────────────────
// 7. Задачи
// ──────────────────────────────────────────────
$tasks = [
    ['client' => 0, 'title' => 'Отправить КП на корпоративный тариф',    'due' => $ago(-2), 'user' => $ownerId,             'type' => 'task'],
    ['client' => 2, 'title' => 'Позвонить по интеграции с 1С',           'due' => $ago(-1), 'user' => $agentIds[1] ?? $ownerId, 'type' => 'reminder'],
    ['client' => 5, 'title' => 'Выставить счёт ЗАО Новак',               'due' => $ago(0),  'user' => $agentIds[2] ?? $ownerId, 'type' => 'task'],
    ['client' => 1, 'title' => 'Проверить доставку письма восстановления','due' => $ago(-1), 'user' => $ownerId,             'type' => 'task'],
    ['client' => 4, 'title' => 'Согласовать продление поддержки',         'due' => $ago(-3), 'user' => $ownerId,             'type' => 'reminder'],
];

foreach ($tasks as $i => $t) {
    $cid = $clientIds[$t['client']] ?? $clientIds[0];
    $exists = Database::fetch('SELECT id FROM tasks WHERE company_id=? AND title=?', [$companyId, $t['title']]);
    if (!$exists) {
        Database::insert('tasks', [
            'company_id'      => $companyId,
            'client_id'       => $cid,
            'conversation_id' => $convIds[$i] ?? null,
            'assigned_user_id'=> $t['user'],
            'title'           => $t['title'],
            'due_at'          => $t['due'],
            'status'          => 'open',
            'type'            => $t['type'],
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
    }
}

// ──────────────────────────────────────────────
// 8. Готовые ответы
// ──────────────────────────────────────────────
$replies = [
    ['shortcut' => 'hello',   'title' => 'Приветствие',          'body' => 'Здравствуйте! Чем могу помочь?'],
    ['shortcut' => 'bye',     'title' => 'Прощание',             'body' => 'Спасибо за обращение! Если появятся вопросы — пишите. Хорошего дня!'],
    ['shortcut' => 'wait',    'title' => 'Попросить подождать',  'body' => 'Одну минуту, уточняю информацию.'],
    ['shortcut' => 'price',   'title' => 'Ответ о ценах',        'body' => 'Актуальные тарифы доступны на нашем сайте в разделе «Тарифы». Могу рассказать подробнее!'],
    ['shortcut' => 'trial',   'title' => 'Пробный период',       'body' => 'У нас есть 14-дневный бесплатный пробный период без ввода карты. Регистрация на сайте займёт 2 минуты.'],
    ['shortcut' => 'api',     'title' => 'Про API',              'body' => 'Документация по API доступна по адресу: https://moschat.online/docs/api. Если нужна помощь с интеграцией — подключим технического специалиста.'],
];

foreach ($replies as $r) {
    $exists = Database::fetch('SELECT id FROM saved_replies WHERE company_id=? AND shortcut=?', [$companyId, $r['shortcut']]);
    if (!$exists) {
        Database::insert('saved_replies', [
            'company_id'  => $companyId,
            'shortcut'    => $r['shortcut'],
            'title'       => $r['title'],
            'body'        => $r['body'],
            'created_by'  => $ownerId,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }
}

echo "Test data seeded for company #{$companyId}\n";
