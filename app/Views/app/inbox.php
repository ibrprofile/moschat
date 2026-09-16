<div class="inbox" id="inbox-root" data-conversation-id="<?= e($conversationId ?? '') ?>">
  <aside class="inbox-list-pane" aria-label="Список диалогов">
    <div class="inbox-toolbar">
      <div class="inbox-filters" role="tablist">
        <button type="button" class="chip chip-outline is-active" data-filter="all">Все</button>
        <button type="button" class="chip chip-outline" data-filter="new">Новые</button>
        <button type="button" class="chip chip-outline" data-filter="unassigned">Без оператора</button>
        <button type="button" class="chip chip-outline" data-filter="mine">Мои</button>
        <button type="button" class="chip chip-outline" data-filter="pending">В работе</button>
        <button type="button" class="chip chip-outline" data-filter="closed">Закрытые</button>
      </div>
      <div class="search-wrap">
        <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input class="input input-sm search-input" type="search" id="inbox-search" placeholder="Поиск диалогов…" aria-label="Поиск диалогов">
      </div>
    </div>
    <div class="inbox-list" id="inbox-list">
      <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="conv-item skeleton-conv">
          <div class="avatar avatar-sm skeleton-avatar"></div>
          <div class="conv-text">
            <div class="skeleton-line w-28 h-4"></div>
            <div class="skeleton-line w-52 h-3 mt-1"></div>
          </div>
          <div class="conv-meta"><div class="skeleton-line w-16 h-3 ml-auto"></div></div>
        </div>
      <?php endfor; ?>
    </div>
  </aside>
  <section class="inbox-thread-pane" id="inbox-thread" aria-label="Диалог">
    <div class="empty-state">
      <div class="empty-illustration">
        <svg class="icon icon-xl empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      </div>
      <h2>Выберите диалог</h2>
      <p>Отвечайте клиентам в реальном времени, назначайте сотрудников и добавляйте теги.</p>
    </div>
  </section>
  <aside class="inbox-client-pane" id="inbox-client" aria-label="Карточка клиента">
    <div class="empty-state compact">
      <h3>Карточка клиента</h3>
      <p>Данные из CRM откроются вместе с диалогом.</p>
    </div>
  </aside>
</div>
