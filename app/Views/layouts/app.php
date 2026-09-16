<?php
/** @var array $user */
/** @var array $company */
/** @var array $membership */
/** @var array $plan */
/** @var string $nav */
/** @var string $csrf */
$icons = [
  'inbox' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/></svg>',
  'crm' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  'visitors' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>',
  'analytics' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 15 4-4 4 4 5-5"/></svg>',
  'employees' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  'departments' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>',
  'settings' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>',
  'docs' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
  'menu' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>',
  'close' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
  'collapse' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="m9 8 4 4-4 4"/></svg>',
  'logout' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>',
  'check' => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
];
$items = [
  'inbox' => ['Входящие', '/app/inbox', 'inbox'],
  'crm' => ['CRM', '/app/crm', 'crm'],
  'visitors' => ['Посетители', '/app/visitors', 'visitors'],
  'analytics' => ['Аналитика', '/app/analytics', 'analytics'],
  'employees' => ['Сотрудники', '/app/employees', 'employees'],
  'departments' => ['Отделы', '/app/departments', 'departments'],
  'settings' => ['Настройки', '/app/settings', 'settings'],
  'docs' => ['Документация', '/app/docs', 'docs'],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title><?= e($title ?? 'MosChat') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
  <div class="app-shell" id="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Основная навигация">
      <div class="sidebar-top">
        <a class="sidebar-logo" href="/app/inbox" aria-label="MosChat">
          <span class="sidebar-logo-mark"></span>
          <span class="sidebar-logo-text">MosChat</span>
        </a>
        <div class="sidebar-controls">
          <button type="button" class="sidebar-collapse btn-icon-ghost" id="sidebar-collapse" aria-label="Свернуть меню" title="Свернуть меню">
            <?= $icons['collapse'] ?>
          </button>
          <button type="button" class="sidebar-close btn-icon-ghost" id="sidebar-close" aria-label="Закрыть меню" title="Закрыть">
            <?= $icons['close'] ?>
          </button>
        </div>
      </div>
      <nav class="sidebar-nav">
        <?php foreach ($items as $key => [$label, $href, $iconKey]): ?>
          <a class="nav-item <?= $nav === $key ? 'is-active' : '' ?>" href="<?= e($href) ?>" title="<?= e($label) ?>">
            <?= $icons[$iconKey] ?? '' ?>
            <span class="nav-label"><?= e($label) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="sidebar-footer">
        <div class="company-chip">
          <span class="company-name"><?= e($company['name'] ?? 'Компания') ?></span>
          <span class="plan-badge"><?= e(mb_strtoupper((string) ($plan['key'] ?? 'free'))) ?></span>
        </div>
        <a class="user-chip" href="/app/profile" title="<?= e($user['name'] ?? '') ?>">
          <span class="avatar avatar-sm avatar-presence online"><?= e(mb_substr($user['name'] ?? 'U', 0, 1)) ?></span>
          <span class="user-meta">
            <span class="user-name"><?= e($user['name'] ?? '') ?></span>
            <span class="user-status"><span class="dot online"></span> онлайн</span>
          </span>
        </a>
        <form method="post" action="/logout" id="logout-form">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button type="submit" class="btn btn-ghost btn-block logout-btn">
            <?= $icons['logout'] ?>
            <span class="nav-label">Выйти</span>
          </button>
        </form>
      </div>
    </aside>
    <div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>
    <main class="main">
      <header class="main-header">
        <button type="button" class="btn btn-ghost mobile-nav-toggle" id="sidebar-open" aria-label="Открыть меню">
          <?= $icons['menu'] ?>
        </button>
        <h1 class="page-title"><?= e($pageTitle ?? '') ?></h1>
        <div class="header-spacer"></div>
      </header>
      <div class="main-content" id="main-content">
        <?= $content ?>
      </div>
    </main>
  </div>
  <div id="toast-root" class="toast-root" aria-live="polite" aria-atomic="true"></div>
  <div id="modal-root" class="modal-root" aria-hidden="true"></div>
  <script src="/assets/js/app.js" defer></script>
  <?php if (($nav ?? '') === 'inbox'): ?>
    <script src="/assets/js/inbox.js" defer></script>
  <?php elseif (($nav ?? '') === 'crm'): ?>
    <script src="/assets/js/crm.js" defer></script>
  <?php elseif (($nav ?? '') === 'employees'): ?>
    <script src="/assets/js/employees.js" defer></script>
  <?php elseif (($nav ?? '') === 'settings'): ?>
    <script src="/assets/js/settings.js" defer></script>
  <?php elseif (($nav ?? '') === 'departments'): ?>
    <script src="/assets/js/departments.js" defer></script>
  <?php elseif (($nav ?? '') === 'visitors'): ?>
    <script src="/assets/js/visitors.js" defer></script>
  <?php elseif (($nav ?? '') === 'analytics'): ?>
    <script src="/assets/js/analytics.js" defer></script>
  <?php endif; ?>
</body>
</html>
