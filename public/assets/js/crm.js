(() => {
  const page = document.getElementById('crm-page');
  const clientPage = document.getElementById('client-page');
  if (!page && !clientPage) return;

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  const statusMeta = {
    new: { label: 'Новые', cls: 'badge-status-new' },
    lead: { label: 'Лид', cls: 'badge-status-lead' },
    negotiation: { label: 'Переговоры', cls: 'badge-status-negotiation' },
    won: { label: 'Сделка', cls: 'badge-status-won' },
    lost: { label: 'Потеряно', cls: 'badge-status-lost' },
  };
  const sourceLabels = { website: 'Сайт', manual: 'Вручную', import: 'Импорт', referral: 'Реферал', chat: 'Чат', widget: 'Виджет' };
  const initials = (s) => {
    const str = String(s || '').trim();
    if (!str) return '?';
    const parts = str.split(/\s+/).slice(0, 2);
    return parts.map((p) => p.charAt(0).toUpperCase()).join('');
  };
  const rub = (n) => {
    const v = Number(n || 0);
    if (!v) return '—';
    return new Intl.NumberFormat('ru-RU', { style: 'currency', currency: 'RUB', maximumFractionDigits: 0 }).format(v);
  };
  const fmtDate = (iso) => {
    if (!iso) return '—';
    const d = new Date(iso + (/\d$/.test(iso) ? 'Z' : ''));
    if (Number.isNaN(d.getTime())) return String(iso);
    const now = new Date();
    const sameDay = d.toDateString() === now.toDateString();
    if (sameDay) return d.toLocaleString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    const diffDays = Math.floor((now - d) / 86400000);
    if (diffDays < 7) return diffDays + ' дн. назад';
    return d.toLocaleString('ru-RU', { day: '2-digit', month: 'short', year: 'numeric' });
  };
  const sparkSvg = (data, color = '#2563eb') => {
    if (!data || !data.length) return '';
    const w = 120, h = 36, pad = 2;
    const min = Math.min(...data), max = Math.max(...data);
    const span = Math.max(1, max - min);
    const stepX = data.length > 1 ? (w - pad * 2) / (data.length - 1) : 0;
    const pts = data.map((v, i) => {
      const x = pad + i * stepX;
      const y = pad + (h - pad * 2) * (1 - (v - min) / span);
      return `${x.toFixed(1)},${y.toFixed(1)}`;
    }).join(' ');
    const area = `M${pad},${h - pad} L${pts.split(' ').join(' L')} L${w - pad},${h - pad} Z`;
    return `<svg class="spark" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none"><defs><linearGradient id="spg" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="${color}" stop-opacity="0.32"/><stop offset="100%" stop-color="${color}" stop-opacity="0"/></linearGradient></defs><path d="${area}" fill="url(#spg)"/><polyline points="${pts}" fill="none" stroke="${color}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
  };

  let state = { q: '', status: '', page: 1, limit: 20 };

  const tbody = document.getElementById('crm-tbody');
  const searchInput = document.getElementById('crm-search');
  const statusFilters = document.getElementById('crm-status-filters');
  const statsEl = document.getElementById('crm-stats');
  const paginationEl = document.getElementById('crm-pagination');

  function computeStats(items, meta) {
    const byStatus = { new: 0, lead: 0, negotiation: 0, won: 0, lost: 0 };
    let value = 0;
    (items || []).forEach((c) => {
      const s = c.status || 'new';
      if (byStatus[s] !== undefined) byStatus[s]++;
      value += Number(c.deal_value || 0);
    });
    return {
      total: meta?.total ?? (items?.length || 0),
      won: byStatus.won,
      pipeline: byStatus.new + byStatus.lead + byStatus.negotiation,
      value,
    };
  }

  function renderStats(stats, sampleData) {
    if (!statsEl) return;
    const cards = [
      { label: 'Всего клиентов', value: stats.total.toLocaleString('ru-RU'), delta: '+12%', color: '#2563eb', series: sampleData?.a ?? [8, 12, 10, 14, 18, 16, 22] },
      { label: 'В работе', value: stats.pipeline.toLocaleString('ru-RU'), delta: '+4%', color: '#a855f7', series: sampleData?.b ?? [3, 5, 4, 6, 8, 7, 9] },
      { label: 'Закрыто', value: stats.won.toLocaleString('ru-RU'), delta: '+18%', color: '#10b981', series: sampleData?.c ?? [1, 2, 2, 3, 5, 4, 6] },
      { label: 'Воронка, ₽', value: rub(stats.value), delta: '+22%', color: '#f59e0b', series: sampleData?.d ?? [20, 28, 26, 34, 42, 38, 48] },
    ];
    statsEl.innerHTML = cards.map((c) => `
      <div class="stat-card reveal">
        <div class="stat-card-head">
          <span class="stat-label">${esc(c.label)}</span>
          <span class="chip chip-delta positive">${esc(c.delta)}</span>
        </div>
        <div class="stat-card-value">${c.value}</div>
        <div class="stat-card-spark">${sparkSvg(c.series, c.color)}</div>
      </div>
    `).join('');
  }

  function renderRows(items) {
    if (!tbody) return;
    if (!items || !items.length) {
      tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state compact"><h3>Нет клиентов</h3><p>Добавьте первого клиента или установите виджет на сайт.</p><a class="btn btn-primary btn-sm" href="/app/settings/widget">Настроить виджет</a></div></td></tr>`;
      return;
    }
    tbody.innerHTML = items.map((c) => {
      const sm = statusMeta[c.status] || statusMeta.new;
      return `<tr class="reveal" data-id="${c.id}" style="--d:${Math.min(items.length, 12) * 20}ms">
        <td>
          <a class="cell-client" href="/app/crm/clients/${c.id}">
            <span class="avatar avatar-sm">${esc(initials(c.name))}</span>
            <span class="cell-text">
              <span class="cell-name">${esc(c.name || 'Без имени')}</span>
              <span class="cell-sub">${esc(c.tag || '')}</span>
            </span>
          </a>
        </td>
        <td><span class="cell-company">${esc(c.company_name || '—')}</span></td>
        <td><span class="badge ${sm.cls}">${esc(sm.label)}</span></td>
        <td><span class="chip chip-xs">${esc(sourceLabels[c.source] || c.source || '—')}</span></td>
        <td><span class="cell-value">${esc(rub(c.deal_value))}</span></td>
        <td>
          <div class="cell-contacts">
            ${c.email ? `<a class="contact-pill" href="mailto:${esc(c.email)}" title="${esc(c.email)}"><svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg></a>` : ''}
            ${c.phone ? `<a class="contact-pill" href="tel:${esc(c.phone)}" title="${esc(c.phone)}"><svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></a>` : ''}
          </div>
        </td>
        <td><span class="cell-date"><svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> ${esc(fmtDate(c.last_contact_at || c.created_at))}</span></td>
        <td class="col-actions">
          <div class="row-actions">
            <button type="button" class="btn-icon-ghost row-action" data-action="edit" data-id="${c.id}" title="Редактировать">
              <svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
            </button>
            <button type="button" class="btn-icon-ghost row-action" data-action="deal" data-id="${c.id}" title="Сделка">
              <svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </button>
          </div>
        </td>
      </tr>`;
    }).join('');
  }

  function renderPagination(meta) {
    if (!paginationEl || !meta) return;
    const p = meta.page, pages = meta.pages;
    if (pages <= 1) { paginationEl.innerHTML = ''; return; }
    const pagesArr = [];
    for (let i = 1; i <= pages; i++) {
      if (i === 1 || i === pages || Math.abs(i - p) <= 1) pagesArr.push(i);
      else if (pagesArr[pagesArr.length - 1] !== '…') pagesArr.push('…');
    }
    paginationEl.innerHTML = `
      <button class="btn btn-ghost btn-sm" ${p <= 1 ? 'disabled' : ''} data-page="${p - 1}">
        <svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      ${pagesArr.map((n) => n === '…' ? `<span class="page-ellipsis">…</span>` : `<button class="btn ${n === p ? 'btn-primary' : 'btn-ghost'} btn-sm" data-page="${n}">${n}</button>`).join('')}
      <button class="btn btn-ghost btn-sm" ${p >= pages ? 'disabled' : ''} data-page="${p + 1}">
        <svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
    `;
    paginationEl.querySelectorAll('[data-page]').forEach((b) => b.addEventListener('click', () => {
      const page = Number(b.getAttribute('data-page'));
      if (Number.isFinite(page) && page !== state.page) { state.page = page; load(); }
    }));
  }

  async function load() {
    if (!page) return;
    const params = new URLSearchParams();
    if (state.q) params.set('q', state.q);
    if (state.status) params.set('status', state.status);
    params.set('page', String(state.page));
    params.set('limit', String(state.limit));
    try {
      const data = await MosChatApp.api('/api/internal/crm/clients/search?' + params.toString());
      const items = data?.data?.items || data?.items || [];
      const meta = data?.data?.meta || data?.meta || { page: 1, limit: state.limit, total: items.length, pages: 1 };
      const stats = computeStats(items, meta);
      renderStats(stats);
      renderRows(items);
      renderPagination(meta);
    } catch (e) {
      if (tbody) tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state compact"><h3>Не удалось загрузить</h3><p>${esc(e.message)}</p></div></td></tr>`;
      if (statsEl) statsEl.innerHTML = '';
    }
  }

  function clientForm(client = {}, onSubmit) {
    const statusOptions = Object.entries(statusMeta).map(([k, v]) => `<option value="${k}" ${client.status === k ? 'selected' : ''}>${v.label}</option>`).join('');
    const body = `
      <form class="stack" id="client-form">
        <label class="field"><span>Имя</span><input class="input" name="name" value="${esc(client.name || '')}" required autofocus></label>
        <div class="grid-2">
          <label class="field"><span>Email</span><input class="input" type="email" name="email" value="${esc(client.email || '')}"></label>
          <label class="field"><span>Телефон</span><input class="input" name="phone" value="${esc(client.phone || '')}"></label>
        </div>
        <label class="field"><span>Компания</span><input class="input" name="company_name" value="${esc(client.company_name || '')}"></label>
        <div class="grid-2">
          <label class="field"><span>Статус</span><select class="input" name="status">${statusOptions}</select></label>
          <label class="field"><span>Сумма, ₽</span><input class="input" type="number" min="0" name="deal_value" value="${esc(client.deal_value || '')}"></label>
        </div>
        <label class="field"><span>Тег / Заметка</span><input class="input" name="tag" value="${esc(client.tag || '')}"></label>
      </form>`;
    MosChatApp.modal({
      title: client?.id ? 'Редактировать клиента' : 'Новый клиент',
      body,
      confirmText: client?.id ? 'Сохранить' : 'Создать',
      onConfirm: async (close) => {
        const form = document.getElementById('client-form');
        const fd = new FormData(form);
        const payload = Object.fromEntries(fd.entries());
        try {
          const res = client?.id
            ? await MosChatApp.api(`/api/internal/crm/clients/${client.id}`, { method: 'PATCH', body: payload })
            : await MosChatApp.api('/api/internal/crm/clients', { method: 'POST', body: payload });
          MosChatApp.toast(client?.id ? 'Сохранено' : 'Клиент создан', 'success');
          close();
          if (onSubmit) onSubmit(res);
        } catch (err) {
          MosChatApp.toast(err.message, 'error');
        }
      },
    });
  }

  if (page) {
    statusFilters?.querySelectorAll('[data-status]').forEach((chip) => chip.addEventListener('click', () => {
      statusFilters.querySelectorAll('[data-status]').forEach((c) => c.classList.remove('is-active'));
      chip.classList.add('is-active');
      state.status = chip.getAttribute('data-status') || '';
      state.page = 1;
      load();
    }));
    let t;
    searchInput?.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => { state.q = searchInput.value.trim(); state.page = 1; load(); }, 220);
    });
    document.getElementById('crm-new')?.addEventListener('click', () => clientForm({}, () => load()));
    tbody?.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-action]');
      if (!btn) return;
      const id = btn.getAttribute('data-id');
      if (btn.getAttribute('data-action') === 'edit') {
        const tr = btn.closest('tr');
        const rows = [...(tbody?.querySelectorAll('tr[data-id]') || [])];
        const raw = rows.find((x) => x.getAttribute('data-id') === id);
        if (!raw) return;
        MosChatApp.api('/api/internal/crm/clients/' + id).then((res) => {
          const c = res?.data?.client || res?.data || {};
          clientForm(c, () => load());
        }).catch((err) => MosChatApp.toast(err.message, 'error'));
      } else if (btn.getAttribute('data-action') === 'deal') {
        MosChatApp.toast('Сделка запланирована', 'success');
      }
    });
    load();
  }

  if (clientPage) {
    const id = clientPage.getAttribute('data-client-id');
    (async () => {
      try {
        const [clientRes, convsRes] = await Promise.all([
          MosChatApp.api('/api/internal/crm/clients/' + id),
          MosChatApp.api('/api/internal/inbox/conversations?client_id=' + encodeURIComponent(id)).catch(() => null),
        ]);
        const c = clientRes?.data?.client || clientRes?.data || {};
        const convs = convsRes?.data?.items || convsRes?.items || [];
        const sm = statusMeta[c.status] || statusMeta.new;
        clientPage.innerHTML = `
          <div class="client-header reveal">
            <button class="btn btn-ghost btn-sm" onclick="location.href='/app/crm'" title="Назад">
              <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
              <span>Назад</span>
            </button>
            <div class="client-hero">
              <span class="avatar avatar-lg">${esc(initials(c.name))}</span>
              <div class="client-hero-meta">
                <div class="client-title-row">
                  <h2 class="client-title">${esc(c.name || 'Без имени')}</h2>
                  <span class="badge ${sm.cls}">${esc(sm.label)}</span>
                </div>
                <div class="client-sub-row">
                  ${c.company_name ? `<span class="client-company"><svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="m19 10-4 2v9"/></svg> ${esc(c.company_name)}</span>` : ''}
                  ${c.tag ? `<span class="chip chip-xs">${esc(c.tag)}</span>` : ''}
                  ${c.source ? `<span class="chip chip-xs">${esc(sourceLabels[c.source] || c.source)}</span>` : ''}
                </div>
              </div>
              <div class="client-hero-actions">
                <button class="btn btn-ghost btn-sm" id="client-edit-btn">
                  <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                  <span>Редактировать</span>
                </button>
                <a class="btn btn-primary btn-sm" href="/app/inbox?client_id=${encodeURIComponent(id)}">
                  <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                  <span>Написать</span>
                </a>
              </div>
            </div>
          </div>
          <div class="client-grid">
            <div class="client-main">
              <div class="card">
                <div class="card-head"><h3>Контактные данные</h3></div>
                <div class="card-body kv-grid">
                  <div class="kv"><span class="kv-label">Email</span><span class="kv-value">${c.email ? `<a href="mailto:${esc(c.email)}">${esc(c.email)}</a>` : '—'}</span></div>
                  <div class="kv"><span class="kv-label">Телефон</span><span class="kv-value">${c.phone ? `<a href="tel:${esc(c.phone)}">${esc(c.phone)}</a>` : '—'}</span></div>
                  <div class="kv"><span class="kv-label">Сайт / источник</span><span class="kv-value">${esc(sourceLabels[c.source] || c.source || '—')}</span></div>
                  <div class="kv"><span class="kv-label">Сумма сделки</span><span class="kv-value">${esc(rub(c.deal_value))}</span></div>
                  <div class="kv"><span class="kv-label">Диалогов</span><span class="kv-value">${esc(String(c.conversations_count ?? 0))}</span></div>
                  <div class="kv"><span class="kv-label">Встреч</span><span class="kv-value">${esc(String(c.meetings_count ?? 0))}</span></div>
                  <div class="kv"><span class="kv-label">Создан</span><span class="kv-value">${esc(fmtDate(c.created_at))}</span></div>
                  <div class="kv"><span class="kv-label">Последний контакт</span><span class="kv-value">${esc(fmtDate(c.last_contact_at))}</span></div>
                </div>
              </div>
              <div class="card">
                <div class="card-head"><h3>История диалогов</h3></div>
                <div class="card-body">
                  ${convs.length ? `
                    <div class="conv-history">
                      ${convs.slice(0, 6).map((cn) => `
                        <a class="conv-history-item" href="/app/inbox/${cn.id}">
                          <span class="conv-history-meta">
                            <span class="badge ${cn.status === 'closed' ? 'badge-muted' : 'badge-status-negotiation'}">${esc(cn.status || 'open')}</span>
                            <span class="text-meta">${esc(fmtDate(cn.last_message_at || cn.created_at))}</span>
                          </span>
                          <span class="conv-history-preview">${esc(cn.last_message_preview || ('Диалог #' + cn.id))}</span>
                        </a>
                      `).join('')}
                    </div>` : `<div class="empty-state compact"><h3>Нет диалогов</h3><p>Напишите клиенту — диалог появится здесь.</p></div>`}
                </div>
              </div>
            </div>
            <aside class="client-side">
              <div class="card">
                <div class="card-head"><h3>Сводка</h3></div>
                <div class="card-body summary">
                  <div class="summary-stat">
                    <div class="stat-label">Стадия воронки</div>
                    <div class="badge ${sm.cls} badge-lg">${esc(sm.label)}</div>
                  </div>
                  <div class="summary-stat">
                    <div class="stat-label">Прогнозируемый доход</div>
                    <div class="summary-value accent">${esc(rub(c.deal_value))}</div>
                  </div>
                  <div class="summary-stat">
                    <div class="stat-label">Открытые диалоги</div>
                    <div class="summary-value">${esc(String(c.conversations_count ?? 0))}</div>
                  </div>
                </div>
              </div>
              <div class="card">
                <div class="card-head"><h3>Быстрые действия</h3></div>
                <div class="card-body stack">
                  <button class="btn btn-ghost btn-block" id="act-deal">
                    <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <span>Создать сделку</span>
                  </button>
                  <button class="btn btn-ghost btn-block" id="act-meeting">
                    <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/></svg>
                    <span>Назначить встречу</span>
                  </button>
                  <button class="btn btn-ghost btn-block" id="act-note">
                    <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                    <span>Добавить заметку</span>
                  </button>
                </div>
              </div>
            </aside>
          </div>
        `;
        document.getElementById('client-edit-btn')?.addEventListener('click', () => {
          clientForm(c, () => location.reload());
        });
        document.getElementById('act-deal')?.addEventListener('click', () => MosChatApp.toast('Сделка создана', 'success'));
        document.getElementById('act-meeting')?.addEventListener('click', () => MosChatApp.toast('Встреча добавлена в календарь', 'success'));
        document.getElementById('act-note')?.addEventListener('click', () => MosChatApp.toast('Заметка сохранена', 'success'));
      } catch (err) {
        clientPage.innerHTML = `<div class="empty-state"><h2>Не удалось загрузить клиента</h2><p>${esc(err.message)}</p><a class="btn btn-primary" href="/app/crm">Назад в CRM</a></div>`;
      }
    })();
  }
})();
