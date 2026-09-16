<div class="page-panel">
  <div class="page-toolbar">
    <div class="input-wrap input-icon-left" style="min-width:260px;flex:0 0 auto;">
      <svg class="icon icon-sm input-icon-left" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
      <input class="input" type="search" id="emp-search" placeholder="Поиск по имени или email">
    </div>
    <div class="spacer"></div>
    <button type="button" class="btn btn-primary" id="emp-invite">
      <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
      Пригласить
    </button>
  </div>
  <div class="stack">
    <section id="emp-section-team" class="section-card reveal" style="--d:30ms;">
      <div class="row-between" style="margin-bottom:16px;">
        <div>
          <h2 class="section-title" style="margin:0;">Команда</h2>
          <div class="text-secondary" style="margin-top:2px;font-size:13px;">Активные участники рабочего пространства</div>
        </div>
        <span class="badge badge-neutral" id="emp-count">0</span>
      </div>
      <div id="employees-list">
        <div class="empty-state compact">
          <div class="empty-state-icon">
            <svg class="icon icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <h2>Команда</h2>
          <p>Пригласите коллег по email — они присоединятся к workspace.</p>
        </div>
      </div>
    </section>
    <section id="emp-section-invites" class="section-card reveal" style="--d:70ms;display:none;">
      <div class="row-between" style="margin-bottom:16px;">
        <div>
          <h2 class="section-title" style="margin:0;">Ожидают приглашения</h2>
          <div class="text-secondary" style="margin-top:2px;font-size:13px;">Ссылки действительны 7 дней</div>
        </div>
        <span class="badge badge-warning" id="emp-invite-count">0</span>
      </div>
      <div id="invites-list"></div>
    </section>
  </div>
</div>
<script src="/assets/js/employees.js" defer></script>
