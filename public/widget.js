(function () {
  'use strict';

  if (window.MosChat && window.MosChat.__loaded) return;

  const script = document.currentScript || document.querySelector('script[data-site]');
  const siteKey = script?.getAttribute('data-site');
  if (!siteKey) return;

  const base = (script?.src || '').replace(/\/widget\.js(?:\?.*)?$/, '') || '';
  const apiBase = base;

  const state = {
    open: false,
    ready: false,
    visitorToken: localStorage.getItem('moschat_vt_' + siteKey) || '',
    sessionKey: sessionStorage.getItem('moschat_sk_' + siteKey) || '',
    conversationId: null,
    settings: null,
    messages: [],
    user: {},
    attrs: {},
    handlers: {},
  };

  function emit(event, payload) {
    (state.handlers[event] || []).forEach((fn) => {
      try { fn(payload); } catch (_) {}
    });
  }

  async function api(path, options = {}) {
    const headers = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Site-Key': siteKey,
    };
    if (state.visitorToken) headers['X-Visitor-Token'] = state.visitorToken;
    const res = await fetch(apiBase + path, {
      ...options,
      headers: { ...headers, ...(options.headers || {}) },
      body: options.body ? JSON.stringify(options.body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.success === false) {
      throw new Error(data?.error?.message || 'Widget API error');
    }
    return data.data;
  }

  function css() {
    return `
      :host { all: initial; }
      * { box-sizing: border-box; font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
      .mc-launcher {
        position: fixed; z-index: 2147483000; width: 56px; height: 56px; border-radius: 50%;
        border: 0; cursor: pointer; color: #fff; display: grid; place-items: center;
        box-shadow: 0 8px 24px rgba(17,24,39,.18); transition: transform .16s ease, opacity .16s ease;
      }
      .mc-launcher:hover { transform: scale(1.04); }
      .mc-launcher.br { right: 20px; bottom: 20px; }
      .mc-launcher.bl { left: 20px; bottom: 20px; }
      .mc-panel {
        position: fixed; z-index: 2147483000; width: min(360px, calc(100vw - 24px)); height: min(560px, calc(100vh - 100px));
        background: #fff; border: 1px solid #E5E7EB; border-radius: 16px; overflow: hidden;
        box-shadow: 0 16px 48px rgba(17,24,39,.18); display: flex; flex-direction: column;
        opacity: 0; transform: translateY(8px); pointer-events: none; transition: opacity .16s ease, transform .16s ease;
      }
      .mc-panel.open { opacity: 1; transform: none; pointer-events: auto; }
      .mc-panel.br { right: 20px; bottom: 88px; }
      .mc-panel.bl { left: 20px; bottom: 88px; }
      .mc-head { padding: 14px 16px; color: #fff; display: flex; justify-content: space-between; align-items: center; }
      .mc-head strong { font-size: 14px; }
      .mc-head button { background: transparent; border: 0; color: #fff; font-size: 20px; cursor: pointer; }
      .mc-body { flex: 1; overflow: auto; padding: 14px; background: #F8FAFC; display: flex; flex-direction: column; gap: 8px; }
      .mc-msg { max-width: 85%; padding: 10px 12px; border-radius: 12px; font-size: 13px; line-height: 1.45; background: #fff; border: 1px solid #E5E7EB; color: #1F2937; }
      .mc-msg.me { align-self: flex-end; background: #EFF6FF; border-color: #BFDBFE; }
      .mc-msg.sys { align-self: center; background: transparent; border: 0; color: #6B7280; font-size: 12px; }
      .mc-form, .mc-composer { padding: 12px; border-top: 1px solid #E5E7EB; background: #fff; display: flex; flex-direction: column; gap: 8px; }
      .mc-input { height: 36px; border: 1px solid #E5E7EB; border-radius: 8px; padding: 0 10px; font-size: 13px; }
      textarea.mc-input { height: 64px; padding: 8px 10px; resize: none; }
      .mc-btn { height: 36px; border: 0; border-radius: 8px; background: var(--mc-color, #2563EB); color: #fff; font-weight: 600; cursor: pointer; font-size: 13px; }
      .mc-brand { text-align: center; font-size: 11px; color: #9CA3AF; padding: 6px; }
      .mc-unread {
        position: absolute; top: -4px; right: -4px; min-width: 18px; height: 18px; border-radius: 999px;
        background: #DC2626; color: #fff; font-size: 10px; font-weight: 700; display: none; place-items: center; padding: 0 4px;
      }
    `;
  }

  let host, shadow, panel, bodyEl, launcher, unreadEl, identified = false;

  function mount() {
    host = document.createElement('div');
    host.id = 'moschat-root';
    shadow = host.attachShadow({ mode: 'open' });
    const style = document.createElement('style');
    style.textContent = css();
    shadow.appendChild(style);

    launcher = document.createElement('button');
    launcher.className = 'mc-launcher br';
    launcher.setAttribute('aria-label', 'Open chat');
    launcher.innerHTML = `<svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v7A2.5 2.5 0 0 1 17.5 16H9l-4 4v-4.5A2.5 2.5 0 0 1 4 13.5v-7Z" stroke="currentColor" stroke-width="1.8"/></svg>`;
    unreadEl = document.createElement('span');
    unreadEl.className = 'mc-unread';
    launcher.appendChild(unreadEl);
    launcher.addEventListener('click', () => state.open ? apiClose() : apiOpen());

    panel = document.createElement('div');
    panel.className = 'mc-panel br';
    panel.innerHTML = `<div class="mc-head"><strong>Chat</strong><button type="button" aria-label="Close">×</button></div><div class="mc-body"></div><div class="mc-composer"></div><div class="mc-brand"></div>`;
    bodyEl = panel.querySelector('.mc-body');
    panel.querySelector('.mc-head button').addEventListener('click', apiClose);

    shadow.appendChild(panel);
    shadow.appendChild(launcher);
    document.documentElement.appendChild(host);
  }

  function applySettings(s) {
    state.settings = s;
    const color = s.primary_color || '#2563EB';
    panel.style.setProperty('--mc-color', color);
    launcher.style.background = color;
    panel.querySelector('.mc-head').style.background = color;
    panel.querySelector('.mc-head strong').textContent = s.company_display_name || 'Support';
    const pos = s.position === 'bottom-left' ? 'bl' : 'br';
    launcher.className = 'mc-launcher ' + pos;
    panel.className = 'mc-panel ' + pos + (state.open ? ' open' : '');
    panel.querySelector('.mc-brand').textContent = s.show_branding ? 'Powered by MosChat' : '';
    renderWelcome();
    renderComposer();
  }

  function renderWelcome() {
    if (state.messages.length) return;
    bodyEl.innerHTML = `<div class="mc-msg sys">${escapeHtml(state.settings?.welcome_message || '')}</div>`;
  }

  function renderMessages() {
    bodyEl.innerHTML = state.messages.map((m) => {
      const mine = m.sender_type === 'visitor';
      return `<div class="mc-msg ${mine ? 'me' : ''}">${escapeHtml(m.body || '')}</div>`;
    }).join('') || `<div class="mc-msg sys">${escapeHtml(state.settings?.welcome_message || '')}</div>`;
    bodyEl.scrollTop = bodyEl.scrollHeight;
  }

  function renderComposer() {
    const wrap = panel.querySelector('.mc-composer');
    const needContact = !identified && (state.settings?.collect_name || state.settings?.collect_email || state.settings?.collect_phone);
    if (needContact) {
      wrap.innerHTML = `
        ${state.settings.collect_name ? '<input class="mc-input" data-f="name" placeholder="Имя" value="' + escapeAttr(state.user.name || '') + '">' : ''}
        ${state.settings.collect_email ? '<input class="mc-input" data-f="email" type="email" placeholder="Email" value="' + escapeAttr(state.user.email || '') + '">' : ''}
        ${state.settings.collect_phone ? '<input class="mc-input" data-f="phone" placeholder="Телефон" value="' + escapeAttr(state.user.phone || '') + '">' : ''}
        ${state.settings.privacy_consent_required ? '<label style="font-size:11px;color:#6B7280"><input type="checkbox" data-f="consent"> ' + escapeHtml(state.settings.privacy_text || 'Согласие на обработку данных') + '</label>' : ''}
        <button class="mc-btn" type="button" data-start>Начать чат</button>`;
      wrap.querySelector('[data-start]').addEventListener('click', async () => {
        const payload = { ...state.user };
        wrap.querySelectorAll('[data-f]').forEach((el) => {
          if (el.type === 'checkbox') return;
          payload[el.getAttribute('data-f')] = el.value;
        });
        if (state.settings.privacy_consent_required && !wrap.querySelector('[data-f=consent]')?.checked) {
          alert('Нужно согласие на обработку данных');
          return;
        }
        state.user = payload;
        await api('/api/widget/contact', { method: 'POST', body: payload });
        identified = true;
        renderComposer();
      });
      return;
    }
    wrap.innerHTML = `<textarea class="mc-input" placeholder="Сообщение…"></textarea><button class="mc-btn" type="button">Отправить</button>`;
    const ta = wrap.querySelector('textarea');
    const send = async () => {
      const body = ta.value.trim();
      if (!body) return;
      ta.value = '';
      const data = await api('/api/widget/messages', {
        method: 'POST',
        body: { body, conversation_id: state.conversationId },
      });
      state.conversationId = data.conversation_id;
      state.messages.push(data.message);
      renderMessages();
      emit('message', data.message);
    };
    wrap.querySelector('button').addEventListener('click', () => send().catch((e) => alert(e.message)));
    ta.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        send().catch((e2) => alert(e2.message));
      }
    });
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }
  function escapeAttr(s) { return escapeHtml(s).replace(/`/g, ''); }

  async function boot() {
    mount();
    const data = await api('/api/widget/bootstrap', {
      method: 'POST',
      body: {
        url: location.href,
        referrer: document.referrer || '',
        visitor_token: state.visitorToken || undefined,
        session_key: state.sessionKey || undefined,
      },
    });
    state.visitorToken = data.visitor_token;
    state.sessionKey = data.session_key;
    state.conversationId = data.conversation_id;
    localStorage.setItem('moschat_vt_' + siteKey, state.visitorToken);
    sessionStorage.setItem('moschat_sk_' + siteKey, state.sessionKey);
    applySettings(data.settings || {});
    if (state.conversationId) {
      const msgs = await api('/api/widget/messages?conversation_id=' + state.conversationId);
      state.messages = (msgs.items || []).filter((m) => m.message_type !== 'note');
      identified = true;
      renderMessages();
      renderComposer();
    }
    state.ready = true;
    emit('ready');
    setInterval(pollMessages, 5000);
  }

  async function pollMessages() {
    if (!state.conversationId || !state.open) return;
    try {
      const msgs = await api('/api/widget/messages?conversation_id=' + state.conversationId);
      const items = (msgs.items || []).filter((m) => m.message_type !== 'note');
      if (items.length !== state.messages.length) {
        state.messages = items;
        renderMessages();
      }
    } catch (_) {}
  }

  function apiOpen() {
    state.open = true;
    panel.classList.add('open');
    unreadEl.style.display = 'none';
    emit('open');
  }
  function apiClose() {
    state.open = false;
    panel.classList.remove('open');
    emit('close');
  }

  window.MosChat = {
    __loaded: true,
    open: apiOpen,
    close: apiClose,
    show: () => { launcher.style.display = 'grid'; },
    hide: () => { launcher.style.display = 'none'; panel.classList.remove('open'); state.open = false; },
    isOpen: () => state.open,
    startConversation: apiOpen,
    setUser: (u) => { state.user = { ...state.user, ...u }; identified = !!(u.email || u.phone || u.name); },
    setAttributes: (a) => { state.attrs = { ...state.attrs, ...a }; },
    on: (event, fn) => {
      state.handlers[event] = state.handlers[event] || [];
      state.handlers[event].push(fn);
    },
  };

  // Boot: wait for DOM if loaded with defer/async, otherwise start immediately
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => boot().catch((err) => console.error('[MosChat]', err)));
  } else {
    boot().catch((err) => console.error('[MosChat]', err));
  }
})();
