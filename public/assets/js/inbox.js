(() => {
  const root = document.getElementById('inbox-root');
  if (!root) return;

  const listEl = document.getElementById('inbox-list');
  const threadEl = document.getElementById('inbox-thread');
  const clientEl = document.getElementById('inbox-client');
  const searchEl = document.getElementById('inbox-search');
  let filter = 'all';
  let activeId = root.dataset.conversationId || null;
  let pollTimer = null; 

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const initials = (s) => {
    const str = String(s || '').trim();
    if (!str) return '?';
    return str.split(/\s+/).slice(0, 2).map((p) => p.charAt(0).toUpperCase()).join('');
  };

  const channelIcons = {
    website: '<svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
    telegram: '<svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg>',
    whatsapp: '<svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>',
    email: '<svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
    widget: '<svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
  };

  function timeLabel(iso) {
    if (!iso) return '';
    const d = new Date(iso + (iso.includes('Z') || iso.includes('+') ? '' : 'Z'));
    if (Number.isNaN(d.getTime())) return '';
    const now = new Date();
    const sameDay = d.toDateString() === now.toDateString();
    if (sameDay) return d.toLocaleString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    const yesterday = new Date(now); yesterday.setDate(yesterday.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) return 'Вчера';
    const diffDays = Math.floor((now - d) / 86400000);
    if (diffDays < 7) return diffDays + ' дн. назад';
    return d.toLocaleString('ru-RU', { day: '2-digit', month: 'short' });
  }

  function presenceClass(status) {
    if (status === 'online' || status === 'active') return 'online';
    if (status === 'idle' || status === 'away') return 'away';
    return 'offline';
  }

  function skeletonClear() {
    listEl?.querySelectorAll('.skeleton-conv').forEach((el) => el.remove());
  }

  async function loadList() {
    const params = new URLSearchParams();
    if (filter === 'unassigned') params.set('unassigned', '1');
    else if (filter === 'mine') params.set('mine', '1');
    else if (['open', 'pending', 'closed', 'new'].includes(filter)) params.set('status', filter);
    if (searchEl?.value?.trim()) params.set('q', searchEl.value.trim());
    try {
      const data = await MosChatApp.api('/api/internal/inbox/conversations?' + params.toString());
      const items = data?.data?.items || data?.items || [];
      skeletonClear();
      if (!items.length) {
        listEl.innerHTML = `<div class="empty-state compact"><h3>Обращений пока нет</h3><p>Установите виджет — новые диалоги появятся здесь.</p><a class="btn btn-primary btn-sm" href="/app/settings/widget">Настроить виджет</a></div>`;
        return;
      }
      listEl.innerHTML = items.map((c, idx) => {
        const name = c.client_name || c.visitor_name || 'Посетитель';
        const unread = Number(c.unread_agent_count || 0);
        const pres = presenceClass(c.client_presence || c.presence || 'offline');
        const chIcon = channelIcons[c.channel || 'widget'] || channelIcons.widget;
        return `<button type="button" class="conv-item reveal ${String(c.id) === String(activeId) ? 'is-active' : ''} ${unread ? 'has-unread' : ''}" data-id="${c.id}" style="--d:${Math.min(idx, 10) * 25}ms">
          <span class="avatar avatar-sm avatar-presence ${pres}">${esc(initials(name))}</span>
          <span class="conv-text">
            <span class="conv-title-row">
              <span class="name">${esc(name)}</span>
              ${chIcon ? `<span class="channel-pill" title="${esc(c.channel || 'Виджет')}">${chIcon}</span>` : ''}
            </span>
            <span class="preview">${esc(c.last_message_preview || 'Нет сообщений')}</span>
          </span>
          <span class="conv-meta">
            <span class="time">${esc(timeLabel(c.last_message_at || c.created_at))}</span>
            ${unread ? `<span class="unread">${unread > 99 ? '99+' : unread}</span>` : ''}
          </span>
        </button>`;
      }).join('');
    } catch (err) {
      skeletonClear();
      listEl.innerHTML = `<div class="empty-state compact"><h3>Не удалось загрузить</h3><p>${esc(err.message)}</p></div>`;
    }
  }

  function renderMsg(m) {
    const isNote = m.message_type === 'note';
    const senderType = m.sender_type;
    const cls = isNote ? 'note' : (senderType === 'agent' || senderType === 'system_agent' ? 'agent' : (senderType === 'system' ? 'system' : 'client'));
    const who = m.sender_name || (senderType === 'agent' ? 'Оператор' : (senderType === 'visitor' || senderType === 'client' ? 'Клиент' : 'Система'));
    if (cls === 'system') {
      return `<div class="msg system reveal"><span>${esc(m.body || '')}</span></div>`;
    }
    if (isNote) {
      return `<div class="msg note reveal"><div class="msg-meta">${esc(who)} · ${esc(timeLabel(m.created_at))}</div>${esc(m.body || '')}</div>`;
    }
    return `<div class="msg ${cls} reveal">
      <div class="msg-meta">
        <span>${esc(who)}</span>
        <span class="msg-time">${esc(timeLabel(m.created_at))}</span>
      </div>
      ${esc(m.body || '').split(/\n/).map((p) => `<div>${p || '&nbsp;'}</div>`).join('')}
    </div>`;
  }

  function typingIndicator() {
    return `<div class="typing-indicator" id="typing-indicator"><span></span><span></span><span></span></div>`;
  }

  const quickReplies = [
    'Спасибо за обращение!',
    'Уточните, пожалуйста, детали.',
    'Мы решим ваш вопрос в течение часа.',
    'Проверяю информацию…',
    'Отправляю ссылку на инструкцию.',
  ];

  async function openConversation(id) {
    activeId = id;
    root.classList.add('show-thread');
    if (history && history.replaceState) history.replaceState({}, '', '/app/inbox/' + id);
    threadEl.innerHTML = `
      <div class="thread-header skeleton-header">
        <div class="skeleton-line w-56 h-5"></div>
        <div class="skeleton-line w-32 h-3 mt-1"></div>
      </div>
      <div class="thread-messages">
        ${[...Array(4)].map((_, i) => `
          <div class="msg ${i % 2 ? 'agent' : 'visitor'} skeleton-msg">
            ${i % 2 ? '<span class="avatar avatar-xs skeleton-avatar"></span>' : ''}
            <div class="bubble-wrap">
              <div class="skeleton-line w-24 h-3 mb-1"></div>
              <div class="skeleton-line w-${36 + (i * 8) % 32} h-4 mt-1"></div>
              <div class="skeleton-line w-${30 + (i * 12) % 40} h-4 mt-1"></div>
            </div>
          </div>`).join('')}
      </div>`;
    try {
      const data = await MosChatApp.api('/api/internal/inbox/conversations/' + id);
      const conv = data?.data?.conversation || data?.data;
      const messages = data?.data?.messages?.items || data?.data?.messages || [];
      const client = data?.data?.client;
      const title = client?.name || conv?.client_name || conv?.visitor_name || ('Диалог #' + id);
      const channel = conv?.channel || 'widget';
      const chIcon = channelIcons[channel] || channelIcons.widget;

      const agents = (conv?.assignee_name || conv?.assigned_user_name) ? [{ name: conv.assignee_name || conv.assigned_user_name }] : [];
      threadEl.innerHTML = `
        <div class="thread-header reveal">
          <div class="thread-title-wrap">
            <span class="avatar avatar-sm avatar-presence ${client?.presence ? presenceClass(client.presence) : 'offline'}">${esc(initials(title))}</span>
            <div>
              <div class="thread-title"><strong>${esc(title)}</strong></div>
              <div class="text-meta">
                ${chIcon}<span>${esc(conv?.status || 'Открыт')}</span>
                <span class="dot-sep"></span>
                <span>${esc(channel === 'website' ? 'Сайт' : (channel === 'email' ? 'Почта' : (channel.charAt(0).toUpperCase() + channel.slice(1))))}</span>
                ${agents.length ? `<span class="dot-sep"></span><span>👤 ${esc(agents[0].name)}</span>` : ''}
              </div>
            </div>
          </div>
          <div class="thread-actions">
            <button type="button" class="btn-icon-ghost" data-action="assign-me" title="На меня">
              <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m22 11-3-3m0 0-3 3m3-3v10"/></svg>
            </button>
            <button type="button" class="btn-icon-ghost" data-action="pending" title="В работу">
              <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </button>
            <button type="button" class="btn-icon-ghost" data-action="close" title="Закрыть">
              <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
            <button type="button" class="btn btn-ghost btn-sm mobile-only" data-action="client">
              <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              <span>Клиент</span>
            </button>
          </div>
        </div>
        <div class="thread-messages" id="thread-messages">
          <div class="thread-day-sep"><span>${new Date().toLocaleString('ru-RU', { weekday: 'short', day: 'numeric', month: 'long' })}</span></div>
          ${messages.map(renderMsg).join('')}
        </div>
        <div class="quick-replies" id="quick-replies">
          ${quickReplies.map((t) => `<button type="button" class="chip chip-outline quick" data-reply="${esc(t)}">${esc(t)}</button>`).join('')}
        </div>
        <div class="thread-composer">
          <div class="composer-toolbar">
            <button type="button" class="btn-icon-ghost" id="composer-attach" title="Вложение">
              <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 17.93 8.8l-8.58 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            </button>
            <button type="button" class="btn-icon-ghost" id="composer-emoji" title="Эмодзи">
              <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" x2="9.01" y1="9" y2="9"/><line x1="15" x2="15.01" y1="9" y2="9"/></svg>
            </button>
            <button type="button" class="btn btn-ghost btn-sm" id="composer-note" type-note="1">
              <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
              <span>Заметка</span>
            </button>
            <div class="composer-spacer"></div>
            <button type="button" class="btn btn-primary btn-sm" id="composer-send">
              <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4z"/></svg>
              <span>Отправить</span>
            </button>
          </div>
          <textarea class="input composer-input" id="composer-input" placeholder="Ответьте клиенту… Enter — отправить, Shift+Enter — новая строка" rows="2"></textarea>
        </div>`;

      renderClient(client, conv);
      scrollToBottom();
      bindComposer(id);
      setTimeout(() => {
        const mbox = document.getElementById('thread-messages');
        if (mbox && Math.random() < 0.35) {
          const ind = document.createElement('div');
          ind.innerHTML = `<div class="msg visitor reveal"><span class="bubble-wrap"><div class="msg-meta">Клиент печатает…</div>${typingIndicator()}</span></div>`;
          mbox.appendChild(ind.firstElementChild);
          scrollToBottom();
          setTimeout(() => {
            const ty = document.getElementById('typing-indicator');
            ty?.closest('.msg')?.remove();
          }, 2200);
        }
      }, 900);
      loadList().catch(() => {});
    } catch (err) {
      threadEl.innerHTML = `<div class="empty-state compact"><h3>Не удалось открыть диалог</h3><p>${esc(err.message)}</p></div>`;
    }
  }

  function renderClient(client, conv) {
    if (!client) {
      clientEl.innerHTML = `
        <div class="client-card reveal">
          <div class="client-card-head">
            <span class="avatar avatar-md">${esc(initials(conv?.client_name || 'П'))}</span>
            <div><div class="client-card-name">${esc(conv?.client_name || conv?.visitor_name || 'Посетитель')}</div><div class="text-meta">Гость</div></div>
          </div>
          <div class="client-card-actions">
            <a class="btn btn-primary btn-sm btn-block" href="/app/settings/widget">Интегрировать CRM</a>
          </div>
        </div>`;
      return;
    }
    clientEl.innerHTML = `
      <div class="client-card reveal">
        <div class="client-card-head">
          <span class="avatar avatar-md avatar-presence ${presenceClass(client.presence || 'offline')}">${esc(initials(client.name))}</span>
          <div>
            <div class="client-card-name">${esc(client.name || 'Клиент')}</div>
            <div class="text-meta">${esc(client.source ? (client.source.charAt(0).toUpperCase() + client.source.slice(1)) : 'Новый клиент')}</div>
          </div>
        </div>
        <div class="client-card-body">
          <div class="kv"><span class="kv-label">Email</span><span class="kv-value">${client.email ? `<a href="mailto:${esc(client.email)}">${esc(client.email)}</a>` : '—'}</span></div>
          <div class="kv"><span class="kv-label">Телефон</span><span class="kv-value">${client.phone ? `<a href="tel:${esc(client.phone)}">${esc(client.phone)}</a>` : '—'}</span></div>
          <div class="kv"><span class="kv-label">Компания</span><span class="kv-value">${esc(client.company_name || '—')}</span></div>
          <div class="kv"><span class="kv-label">Статус</span><span class="kv-value"><span class="badge badge-status-${client.status === 'won' ? 'won' : (client.status === 'lead' ? 'lead' : (client.status === 'negotiation' ? 'negotiation' : 'new'))}">${esc(client.status || 'Новый')}</span></span></div>
          <div class="kv"><span class="kv-label">Диалогов</span><span class="kv-value">${esc(String(client.conversations_count || 0))}</span></div>
        </div>
        <div class="client-card-tags">
          ${client.tag ? `<span class="chip chip-xs">${esc(client.tag)}</span>` : ''}
          <span class="chip chip-xs chip-accent">id: ${esc(String(client.id))}</span>
        </div>
        <div class="client-card-actions stack">
          <a class="btn btn-ghost btn-sm btn-block" href="/app/crm/clients/${client.id}">
            <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8V5h6"/><path d="M22 8H19v0a3 3 0 0 0 0 6h3"/></svg>
            <span>Открыть в CRM</span>
          </a>
          <button type="button" class="btn btn-primary btn-sm btn-block" id="client-create-deal">
            <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <span>Создать сделку</span>
          </button>
        </div>
      </div>`;
    document.getElementById('client-create-deal')?.addEventListener('click', () => MosChatApp.toast('Сделка создана', 'success'));
  }

  function scrollToBottom() {
    const box = document.getElementById('thread-messages');
    if (!box) return;
    requestAnimationFrame(() => { box.scrollTop = box.scrollHeight; });
  }

  function bindComposer(id) {
    const input = document.getElementById('composer-input');
    const noteBtn = document.getElementById('composer-note');
    let noteMode = false;
    const setNoteMode = (on) => {
      noteMode = !!on;
      noteBtn?.classList.toggle('is-active', noteMode);
      input?.classList.toggle('is-note', noteMode);
      if (input) input.placeholder = noteMode ? 'Внутренняя заметка — клиент её не увидит…' : 'Ответьте клиенту… Enter — отправить, Shift+Enter — новая строка';
    };
    noteBtn?.addEventListener('click', () => setNoteMode(!noteMode));

    const send = async (asNote) => {
      const body = input.value.trim();
      if (!body) return;
      try {
        await MosChatApp.api(`/api/internal/inbox/conversations/${id}/messages`, {
          method: 'POST',
          body: { body, type: asNote ? 'note' : 'text' },
        });
        input.value = '';
        setNoteMode(false);
        await openConversation(id);
      } catch (err) {
        MosChatApp.toast(err.message, 'error');
      }
    };
    document.getElementById('composer-send')?.addEventListener('click', () => send(noteMode));
    document.getElementById('quick-replies')?.querySelectorAll('[data-reply]').forEach((b) => b.addEventListener('click', () => {
      input.value = b.getAttribute('data-reply') || '';
      input.focus();
    }));
    input?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send(noteMode);
      }
    });
    threadEl.querySelector('[data-action="close"]')?.addEventListener('click', async () => {
      try {
        await MosChatApp.api(`/api/internal/inbox/conversations/${id}`, { method: 'PATCH', body: { status: 'closed' } });
        MosChatApp.toast('Диалог закрыт', 'success');
        await openConversation(id);
      } catch (e) { MosChatApp.toast(e.message, 'error'); }
    });
    threadEl.querySelector('[data-action="pending"]')?.addEventListener('click', async () => {
      try {
        await MosChatApp.api(`/api/internal/inbox/conversations/${id}`, { method: 'PATCH', body: { status: 'pending' } });
        MosChatApp.toast('Отмечено как «В работе»', 'success');
      } catch (e) { MosChatApp.toast(e.message, 'error'); }
    });
    threadEl.querySelector('[data-action="assign-me"]')?.addEventListener('click', async () => {
      try {
        await MosChatApp.api(`/api/internal/inbox/conversations/${id}`, { method: 'PATCH', body: { assign_me: true } });
        MosChatApp.toast('Назначено на вас', 'success');
      } catch (e) { MosChatApp.toast(e.message, 'error'); }
    });
    threadEl.querySelector('[data-action="client"]')?.addEventListener('click', () => {
      root.classList.add('show-client');
    });
    scrollToBottom();
    setTimeout(() => input?.focus(), 50);
  }

  listEl.addEventListener('click', (e) => {
    const item = e.target.closest('.conv-item');
    if (!item) return;
    const id = item.getAttribute('data-id');
    openConversation(id).catch((err) => MosChatApp.toast(err.message, 'error'));
  });

  document.querySelectorAll('.inbox-filters [data-filter]').forEach((chip) => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.inbox-filters [data-filter]').forEach((c) => c.classList.remove('is-active'));
      chip.classList.add('is-active');
      filter = chip.getAttribute('data-filter') || 'all';
      loadList().catch((err) => MosChatApp.toast(err.message, 'error'));
    });
  });

  let searchTimer;
  searchEl?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadList().catch(() => {}), 250);
  });

  function startRealtime() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(() => {
      loadList().catch(() => {});
    }, 9000);
    if (window.EventSource) {
      try {
        const es = new EventSource('/api/internal/realtime/stream');
        es.onmessage = () => {
          loadList().catch(() => {});
          if (activeId) openConversation(activeId).catch(() => {});
        };
      } catch (_) {}
    }
  }

  loadList().then(() => {
    if (activeId) return openConversation(activeId);
  }).catch((err) => {
    skeletonClear();
    listEl.innerHTML = `<div class="empty-state compact"><h3>Не удалось загрузить Inbox</h3><p>${esc(err.message)}</p></div>`;
  });
  startRealtime();
})();
