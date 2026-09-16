(() => {
  const listEl = document.getElementById('employees-list');
  const invitesEl = document.getElementById('invites-list');
  const countEl = document.getElementById('emp-count');
  const inviteCountEl = document.getElementById('emp-invite-count');
  const sectionInvites = document.getElementById('emp-section-invites');
  const searchEl = document.getElementById('emp-search');
  if (!listEl) return;

  const esc = MosChatApp.esc;
  const initials = (s) => {
    const str = String(s || '').trim();
    if (!str) return '?';
    return str.split(/\s+/).slice(0, 2).map((p) => p.charAt(0).toUpperCase()).join('');
  };
  const ICONS = MosChatApp.ICONS;

  let allEmployees = [];
  let allInvites = [];
  let allRoles = [];
  let filter = '';

  const roleMeta = {
    owner: { label: 'Владелец', cls: 'badge-accent' },
    admin: { label: 'Администратор', cls: 'badge-warning' },
    agent: { label: 'Оператор', cls: 'badge-info' },
    viewer: { label: 'Наблюдатель', cls: 'badge-neutral' },
  };

  function roleBadge(key, name) {
    const meta = roleMeta[key] || { cls: 'badge-neutral', label: name || key };
    const label = name || meta.label;
    return `<span class="badge ${meta.cls}">${esc(label)}</span>`;
  }

  function presenceBadge(presence, lastSeen) {
    const p = presence || 'offline';
    const cls = p === 'online' ? 'badge-success' : (p === 'away' ? 'badge-warning' : 'badge-neutral');
    const label = p === 'online' ? 'В сети' : (p === 'away' ? 'Отошёл' : 'Не в сети');
    const dot = `<span class="badge-dot"></span>`;
    return `<span class="badge ${cls}">${dot}${label}</span>`;
  }

  function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso + (iso.includes('Z') || iso.includes('+') ? '' : 'Z'));
    if (Number.isNaN(d.getTime())) return '';
    const now = Date.now();
    const diffMs = now - d.getTime();
    const diffHours = diffMs / 3600000;
    if (diffHours < 24) {
      if (diffHours < 1) {
        const m = Math.max(1, Math.floor(diffMs / 60000));
        return `${m} мин. назад`;
      }
      return `${Math.floor(diffHours)} ч. назад`;
    }
    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 7) return `${diffDays} дн. назад`;
    return d.toLocaleString('ru-RU', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  function daysUntil(iso) {
    if (!iso) return 0;
    const d = new Date(iso + (iso.includes('Z') || iso.includes('+') ? '' : 'Z'));
    if (Number.isNaN(d.getTime())) return 0;
    return Math.max(0, Math.ceil((d.getTime() - Date.now()) / 86400000));
  }

  function matchesFilter(e) {
    if (!filter) return true;
    const q = filter.toLowerCase();
    return ((e.name || '').toLowerCase().includes(q) ||
            (e.email || '').toLowerCase().includes(q));
  }

  function renderEmployees() {
    const items = allEmployees.filter(matchesFilter);
    countEl.textContent = String(allEmployees.length);
    if (!items.length) {
      if (!allEmployees.length) {
        listEl.className = '';
        listEl.innerHTML = `<div class="empty-state compact">
          <div class="empty-state-icon">
            <svg class="icon icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <h2>Команда</h2>
          <p>Пригласите коллег по email — они присоединятся к workspace.</p>
        </div>`;
      } else {
        listEl.className = '';
        listEl.innerHTML = `<div class="empty-state compact"><h3>Ничего не найдено</h3><p>Попробуйте изменить поисковый запрос.</p></div>`;
      }
      return;
    }

    listEl.className = 'table-wrap';
    listEl.innerHTML = `<table class="table">
      <thead><tr>
        <th>Сотрудник</th>
        <th>Роль</th>
        <th>Статус</th>
        <th>Добавлен</th>
        <th style="width:60px;"></th>
      </tr></thead>
      <tbody>
      ${items.map((e, idx) => {
        const isOwner = (e.role_key || '') === 'owner';
        return `<tr class="reveal" style="--d:${Math.min(idx, 10) * 20}ms;">
          <td>
            <div class="table-cell-row">
              <span class="avatar avatar-sm avatar-presence ${(e.presence || 'offline') === 'online' ? 'online' : 'offline'}">${esc(initials(e.name))}</span>
              <div class="cell-info">
                <span class="cell-primary">${esc(e.name)}</span>
                <span class="cell-secondary">${esc(e.email)}</span>
              </div>
            </div>
          </td>
          <td>${roleBadge(e.role_key, e.role_name)}</td>
          <td>${presenceBadge(e.presence, e.last_seen_at)}<div class="cell-secondary" style="margin-top:2px;">${e.last_seen_at ? 'был(а): ' + formatDate(e.last_seen_at) : ''}</div></td>
          <td><span class="text-secondary" style="font-size:13px;">${formatDate(e.created_at)}</span></td>
          <td>
            <div class="cell-actions">
              <button type="button" class="btn-icon-ghost" data-action="details" title="Подробнее" ${isOwner ? 'disabled style="opacity:0.3;pointer-events:none;"' : ''}>
                <svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
              </button>
            </div>
          </td>
        </tr>`;
      }).join('')}
      </tbody></table>`;
    MosChatApp.revealScan?.();
  }

  function renderInvites() {
    inviteCountEl.textContent = String(allInvites.length);
    if (!allInvites.length) {
      sectionInvites.style.display = 'none';
      invitesEl.innerHTML = '';
      return;
    }
    sectionInvites.style.display = '';
    const pending = allInvites.filter(matchesFilter);
    if (!pending.length) {
      invitesEl.innerHTML = `<div class="empty-state compact"><h3>Ничего не найдено</h3><p>Нет приглашений по данному запросу.</p></div>`;
      MosChatApp.revealScan?.();
      return;
    }
    invitesEl.className = 'table-wrap';
    invitesEl.innerHTML = `<table class="table">
      <thead><tr>
        <th>Email</th>
        <th>Роль</th>
        <th>Приглашено</th>
        <th>Действует</th>
        <th style="width:120px;"></th>
      </tr></thead>
      <tbody>
      ${pending.map((i, idx) => {
        const days = daysUntil(i.expires_at);
        const daysCls = days <= 1 ? 'text-danger' : (days <= 2 ? 'text-warning' : 'text-secondary');
        return `<tr class="reveal" style="--d:${Math.min(idx, 10) * 20}ms;">
          <td>
            <div class="table-cell-row">
              <span class="avatar avatar-sm" style="background:var(--color-warning-soft);color:#fbbf24;">
                <svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
              </span>
              <div class="cell-info">
                <span class="cell-primary">${esc(i.email)}</span>
                <span class="cell-secondary">ожидает регистрации</span>
              </div>
            </div>
          </td>
          <td>${roleBadge(i.role_key, i.role_name)}</td>
          <td><span class="text-secondary" style="font-size:13px;">${formatDate(i.created_at)}</span></td>
          <td><span class="${daysCls}" style="font-size:13px;font-weight:500;">${days} дн.</span></td>
          <td>
            <div class="cell-actions" style="opacity:1;">
              <button type="button" class="btn btn-ghost btn-sm" data-action="copy-link" data-id="${i.id}">
                <svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                Ссылка
              </button>
            </div>
          </td>
        </tr>`;
      }).join('')}
      </tbody></table>`;
    MosChatApp.revealScan?.();
  }

  async function load() {
    try {
      const data = await MosChatApp.api('/api/internal/employees');
      allEmployees = (data.data?.employees || []).slice();
      allInvites = (data.data?.invitations || []).slice();
      allRoles = (data.data?.roles || []).slice();
      renderEmployees();
      renderInvites();
    } catch (err) {
      listEl.className = '';
      listEl.innerHTML = `<div class="empty-state compact"><h3>${esc(err.message)}</h3></div>`;
    }
  }

  async function inviteModal() {
    const selectableRoles = allRoles.filter((r) => r.key !== 'owner');
    const roleOptions = selectableRoles.map((r) => {
      const def = r.key === 'agent' ? ' selected' : '';
      return `<option value="${String(r.id)}"${def}>${esc(r.name)} (${esc(r.key)})</option>`;
    }).join('');

    const formHtml = `
      <form id="invite-form" class="stack-sm" onsubmit="event.preventDefault();">
        <label class="field">
          <span>Email сотрудника</span>
          <input class="input" name="email" type="email" placeholder="ivan@company.ru" required autofocus>
        </label>
        <label class="field">
          <span>Роль в команде</span>
          <select class="input" name="role_id" required>${roleOptions}</select>
        </label>
        <div class="alert alert-info" style="padding:10px 12px;font-size:13px;">
          На указанную почту будет отправлено письмо с ссылкой для входа. Ссылка действительна 7 дней.
        </div>
      </form>`;

    MosChatApp.modal({
      title: 'Пригласить сотрудника',
      bodyHtml: formHtml,
      submitText: 'Отправить приглашение',
      submitClass: 'btn-primary',
      cancelText: 'Отмена',
      onSubmit: async ({ close, values }) => {
        if (!values.email || !String(values.email).includes('@')) throw new Error('Введите корректный email');
        if (!values.role_id) throw new Error('Выберите роль');
        const res = await MosChatApp.api('/api/internal/employees/invite', {
          method: 'POST',
          body: { email: values.email, role_id: Number(values.role_id) },
        });
        MosChatApp.toast('Приглашение отправлено на ' + values.email, 'success');
        if (res?.data?.invite_url) {
          try {
            await MosChatApp.copyText(res.data.invite_url);
            MosChatApp.toast('Ссылка приглашения скопирована', 'info', { timeout: 1600 });
          } catch (_) {}
        }
        load().catch(() => {});
        close();
      },
    });
  }

  invitesEl?.addEventListener?.('click', async (e) => {
    const copyBtn = e.target.closest('[data-action="copy-link"]');
    if (!copyBtn) return;
    const row = copyBtn.closest('tr');
    if (!row) return;
    const id = copyBtn.getAttribute('data-id');
    const invite = allInvites.find((i) => String(i.id) === String(id));
    if (!invite) return;
    MosChatApp.modal({
      title: 'Ссылка приглашения',
      size: 'md',
      submitText: 'Скопировать',
      cancelText: 'Закрыть',
      bodyHtml: `<div class="stack-sm">
        <div class="text-secondary" style="font-size:13px;">Отправьте эту ссылку сотруднику, если письмо не дошло:</div>
        <pre class="code-block" id="invite-link-block" style="margin:0;font-size:12px;">${esc(urlFor(invite.email))}</pre>
        <div class="row" style="gap:6px;margin-top:2px;">
          <span class="badge badge-neutral">email: ${esc(invite.email)}</span>
          <span class="badge badge-warning">${daysUntil(invite.expires_at)} дн.</span>
        </div>
      </div>`,
      onSubmit: async ({ close }) => {
        const text = document.getElementById('invite-link-block')?.innerText || '';
        try {
          await MosChatApp.copyText(text);
          MosChatApp.toast('Ссылка скопирована', 'success');
        } catch (e) {
          MosChatApp.toast(e.message, 'error');
        }
        close();
      },
    });
  });

  function urlFor(email) {
    const token = 'INVITE_' + encodeURIComponent(email);
    const base = (window.location.origin || '') + '/register?invite=' + token;
    return base;
  }

  let searchTimer;
  searchEl?.addEventListener('input', () => {
    filter = searchEl.value.trim();
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      renderEmployees();
      renderInvites();
    }, 120);
  });

  document.getElementById('emp-invite')?.addEventListener('click', () => inviteModal());

  load().catch((e) => {
    listEl.innerHTML = `<div class="empty-state compact"><h3>${esc(e.message)}</h3></div>`;
  });
})();
