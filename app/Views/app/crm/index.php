<?php
$statuses = [
  'new' => ['Новые', 'badge-status-new', 'Нерешённые'],
  'lead' => ['Лид', 'badge-status-lead', 'Заинтересованы'],
  'negotiation' => ['Переговоры', 'badge-status-negotiation', 'В работе'],
  'won' => ['Сделка', 'badge-status-won', 'Оплачено'],
  'lost' => ['Потеряно', 'badge-status-lost', 'Отказ'],
];
$sources = [
  'website' => 'Сайт',
  'manual' => 'Вручную',
  'import' => 'Импорт',
  'referral' => 'Реферал',
  'chat' => 'Чат',
];
?>
<div class="page-panel" id="crm-page">
  <div class="page-toolbar">
    <div class="toolbar-left">
      <div class="search-wrap">
        <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input class="input input-sm search-input" type="search" id="crm-search" placeholder="Имя, компания, email, телефон…">
      </div>
      <div class="status-chips" id="crm-status-filters" role="tablist">
        <button type="button" class="chip chip-outline is-active" data-status="">Все</button>
        <?php foreach ($statuses as $key => [$label]): ?>
          <button type="button" class="chip chip-outline" data-status="<?= e($key) ?>"><?= e($label) ?></button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="toolbar-right">
      <button type="button" class="btn btn-ghost btn-sm" id="crm-view-toggle" data-view="table" title="Вид">
        <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
      </button>
      <button type="button" class="btn btn-primary btn-sm" id="crm-new">
        <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        <span>Новый клиент</span>
      </button>
    </div>
  </div>

  <div class="stats-row" id="crm-stats">
    <div class="stat-card skeleton"><div class="stat-label skeleton-line w-24"></div><div class="stat-value skeleton-line w-16 mt-2"></div></div>
    <div class="stat-card skeleton"><div class="stat-label skeleton-line w-24"></div><div class="stat-value skeleton-line w-16 mt-2"></div></div>
    <div class="stat-card skeleton"><div class="stat-label skeleton-line w-24"></div><div class="stat-value skeleton-line w-16 mt-2"></div></div>
    <div class="stat-card skeleton"><div class="stat-label skeleton-line w-24"></div><div class="stat-value skeleton-line w-16 mt-2"></div></div>
  </div>

  <div id="crm-list" class="table-wrap crm-table">
    <table class="table">
      <thead>
        <tr>
          <th>Клиент</th>
          <th>Компания</th>
          <th>Статус</th>
          <th>Источник</th>
          <th>Сумма</th>
          <th>Контакт</th>
          <th>Последний контакт</th>
          <th class="col-actions"></th>
        </tr>
      </thead>
      <tbody id="crm-tbody">
        <?php for ($i = 0; $i < 8; $i++): ?>
          <tr class="skeleton-row">
            <td><div class="cell-client"><div class="avatar avatar-sm skeleton-avatar"></div><div><div class="skeleton-line w-32"></div><div class="skeleton-line w-40 mt-1 text-sm"></div></div></div></td>
            <td><div class="skeleton-line w-36"></div></td>
            <td><div class="skeleton-badge"></div></td>
            <td><div class="skeleton-line w-20"></div></td>
            <td><div class="skeleton-line w-24"></div></td>
            <td><div class="skeleton-line w-32"></div></td>
            <td><div class="skeleton-line w-28"></div></td>
            <td><div class="skeleton-line w-12 ml-auto"></div></td>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
    <div class="table-pagination" id="crm-pagination"></div>
  </div>
</div>
<script src="/assets/js/crm.js" defer></script>
