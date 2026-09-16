(() => {
  document.querySelectorAll('form[data-api]').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      const body = {};
      fd.forEach((v, k) => {
        if (body[k] !== undefined) return;
        const el = form.elements.namedItem(k);
        if (el && el.type === 'checkbox') body[k] = el.checked ? 1 : 0;
        else body[k] = v;
      });
      form.querySelectorAll('input[type=checkbox]').forEach((el) => {
        if (!fd.has(el.name)) body[el.name] = 0;
      });
      try {
        await MosChatApp.api(form.dataset.api, { method: 'POST', body });
        MosChatApp.toast('Настройки сохранены', 'success');
      } catch (err) {
        MosChatApp.toast(err.message, 'error');
      }
    });
  });

  document.querySelectorAll('[data-plan]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      try {
        await MosChatApp.api('/api/internal/billing/plan', { method: 'POST', body: { plan: btn.dataset.plan } });
        MosChatApp.toast('Тариф обновлён', 'success');
        setTimeout(() => location.reload(), 500);
      } catch (err) {
        MosChatApp.toast(err.message, 'error');
      }
    });
  });

  // ================= Widget Preview =================
  const stage = document.getElementById('widget-stage');
  const form = document.getElementById('widget-form');
  const fab = document.getElementById('bubble-fab');
  const chatWin = document.getElementById('chat-window');
  const cwMin = document.getElementById('cw-min');
  const previewBadge = document.getElementById('preview-badge');

  if (form && stage) {
    const esc = MosChatApp.esc;

    const f = {
      color:    document.getElementById('s-color'),
      colorHex: document.getElementById('s-color-hex'),
      name:     document.getElementById('s-name'),
      welcome:  document.getElementById('s-welcome'),
      offline:  document.getElementById('s-offline'),
      position: document.getElementById('s-position'),
      reqName:  document.getElementById('s-name-req'),
      reqEmail: document.getElementById('s-email-req'),
      reqPhone: document.getElementById('s-phone-req'),
      privacy:  document.getElementById('s-privacy'),
    };

    const $ = (id) => document.getElementById(id);

    const sync = () => {
      const color = f.color?.value?.trim() || '#2563EB';
      const name = (f.name?.value || 'MosChat').trim() || 'MosChat';
      const welcome = (f.welcome?.value || '').trim();
      const offline = (f.offline?.value || '').trim();
      const position = f.position?.value || 'bottom-right';
      const reqN = !!f.reqName?.checked;
      const reqE = !!f.reqEmail?.checked;
      const reqP = !!f.reqPhone?.checked;
      const privacy = !!f.privacy?.checked;
      const hasReq = reqN || reqE || reqP;

      // apply to CSS var
      stage.style.setProperty('--wp-accent', color);
      stage.setAttribute('data-position', position);

      // picker hex preview
      if (f.colorHex) f.colorHex.textContent = color.toUpperCase();

      // color apply to FAB, head, send button, typing dots
      if (fab) {
        fab.style.background = color;
        fab.style.setProperty('--bf-color', color);
      }
      if (previewBadge) {
        previewBadge.style.background = `${color}22`;
        previewBadge.style.color = color;
        previewBadge.style.borderColor = `${color}33`;
      }
      const cwHead = $('chat-window-head');
      if (cwHead) cwHead.style.background = color;

      const cwSend = document.querySelector('.cw-send');
      if (cwSend) cwSend.style.background = color;

      // text fields
      const cwName = $('cw-name');
      if (cwName) cwName.textContent = name;
      const agentName = $('cw-agent-name');
      if (agentName) agentName.textContent = name;
      const offName = $('cw-offline-name');
      if (offName) offName.textContent = name;

      const avatar = $('cw-avatar');
      if (avatar) {
        const initials = name.split(/\s+/).filter(Boolean).slice(0, 2)
          .map((p) => p.charAt(0).toUpperCase()).join('') || 'MC';
        avatar.textContent = initials;
      }

      const wBody = $('cw-welcome-body');
      if (wBody) {
        const text = welcome || 'Здравствуйте! Чем можем помочь?';
        wBody.innerHTML = text.split(/\n/).map((p) => `<div>${esc(p) || '&nbsp;'}</div>`).join('');
      }
      const oBody = $('cw-offline-body');
      if (oBody) {
        const text = offline || 'Мы не в сети сейчас. Оставьте контакты — ответим в рабочее время.';
        oBody.innerHTML = text.split(/\n/).map((p) => `<div>${esc(p) || '&nbsp;'}</div>`).join('');
      }

      const hasOffline = !!offline;
      const offGroup = $('cw-offline-group');
      const offBox = $('cw-offline-box');
      if (offGroup) offGroup.style.display = hasOffline ? '' : 'none';
      if (offBox) offBox.style.display = hasOffline ? '' : 'none';

      // required fields
      const reqs = $('cw-reqs');
      if (reqs) reqs.style.display = hasReq ? '' : 'none';
      const reqNInp = $('cw-req-name');
      const reqEInp = $('cw-req-email');
      const reqPInp = $('cw-req-phone');
      if (reqNInp) reqNInp.style.display = reqN ? '' : 'none';
      if (reqEInp) reqEInp.style.display = reqE ? '' : 'none';
      if (reqPInp) reqPInp.style.display = reqP ? '' : 'none';
      const consent = $('cw-consent');
      if (consent) consent.style.display = privacy ? '' : 'none';
    };

    const onInput = () => {
      sync();
      if (previewBadge) {
        previewBadge.classList.remove('badge-info');
        previewBadge.classList.add('badge-success');
        previewBadge.textContent = 'Изменения применены';
        clearTimeout(onInput._t);
        onInput._t = setTimeout(() => {
          previewBadge.classList.remove('badge-success');
          previewBadge.classList.add('badge-info');
          previewBadge.textContent = 'Живое превью';
        }, 1400);
      }
    };

    [f.color, f.name, f.welcome, f.offline, f.position].forEach((el) => {
      el?.addEventListener('input', onInput);
      el?.addEventListener('change', onInput);
    });
    [f.reqName, f.reqEmail, f.reqPhone, f.privacy].forEach((el) => {
      el?.addEventListener('change', onInput);
    });

    document.getElementById('widget-reset')?.addEventListener('click', () => {
      if (f.color) f.color.value = '#2563EB';
      if (f.name) f.name.value = 'MosChat';
      if (f.welcome) f.welcome.value = 'Здравствуйте! Чем можем помочь?';
      if (f.offline) f.offline.value = 'Мы не в сети сейчас. Оставьте контакты — ответим в рабочее время.';
      if (f.position) f.position.value = 'bottom-right';
      [f.reqName, f.reqEmail, f.reqPhone, f.privacy].forEach((el) => { if (el) el.checked = false; });
      sync();
      MosChatApp.toast('Сброшено', 'info', { timeout: 1200 });
    });

    // chat toggle
    const openChat = () => {
      stage?.classList.add('is-open');
    };
    const closeChat = () => {
      stage?.classList.remove('is-open');
    };
    fab?.addEventListener('click', () => {
      if (stage.classList.contains('is-open')) closeChat();
      else openChat();
    });
    cwMin?.addEventListener('click', closeChat);

    sync();
  }

  // ================= Tokens + Webhooks via Modal =================
  document.getElementById('create-token')?.addEventListener('click', async () => {
    const formHtml = `
      <form class="stack-sm" onsubmit="event.preventDefault();">
        <label class="field"><span>Название токена</span>
          <input class="input" name="name" placeholder="Production API" autofocus maxlength="60" required>
          <small>Отображается только в списке токенов</small>
        </label>
      </form>`;
    MosChatApp.modal({
      title: 'Новый API-токен',
      size: 'md',
      bodyHtml: formHtml,
      submitText: 'Создать токен',
      submitClass: 'btn-primary',
      cancelText: 'Отмена',
      onSubmit: async ({ close, values }) => {
        if (!values.name || !String(values.name).trim()) throw new Error('Укажите название');
        const res = await MosChatApp.api('/api/internal/api-tokens', {
          method: 'POST',
          body: { name: values.name.trim() },
        });
        const token = res?.data?.token || res?.data?.plain_token;
        if (!token) throw new Error('Не удалось создать токен');
        close();
        setTimeout(() => {
          MosChatApp.modal({
            title: 'Токен создан',
            size: 'md',
            cancelText: 'Закрыть',
            submitText: 'Скопировать',
            bodyHtml: `<div class="stack-sm">
              <div class="alert alert-warning" style="padding:10px 12px;font-size:13px;">
                Храните токен в безопасном месте — он отображается только один раз.
              </div>
              <pre class="code-block" id="token-display" style="margin:0;font-size:12px;">${esc(token)}</pre>
              <div class="text-secondary" style="font-size:12px;margin-top:-2px;">Имя: <strong class="text-primary">${esc(values.name)}</strong></div>
            </div>`,
            onSubmit: async ({ close }) => {
              const el = document.getElementById('token-display');
              if (el) {
                try {
                  await MosChatApp.copyText(el.innerText);
                  MosChatApp.toast('Токен скопирован', 'success');
                } catch (_) {
                  MosChatApp.toast('Не удалось скопировать', 'error');
                }
              }
              close();
            },
          });
        }, 80);
        setTimeout(() => location.reload(), 2400);
      },
    });
  });

  document.getElementById('create-webhook')?.addEventListener('click', async () => {
    const events = [
      { key: 'message.created', label: 'Новое сообщение' },
      { key: 'conversation.created', label: 'Новый диалог' },
      { key: 'conversation.closed', label: 'Диалог закрыт' },
      { key: 'client.created', label: 'Новый клиент' },
      { key: 'client.updated', label: 'Клиент изменён' },
    ];
    const defaults = ['message.created', 'conversation.created', 'client.created'];
    const eventsHtml = events.map((ev) => {
      const checked = defaults.includes(ev.key) ? 'checked' : '';
      return `<label class="check compact" style="margin:6px 0;">
        <input type="checkbox" name="events" value="${ev.key}" ${checked}> <span>${ev.label} <code style="margin-left:4px;opacity:0.7;">${ev.key}</code></span>
      </label>`;
    }).join('');

    const formHtml = `
      <form class="stack-sm" onsubmit="event.preventDefault();">
        <label class="field"><span>Webhook URL</span>
          <input class="input" name="url" placeholder="https://api.example.com/webhook" autofocus required>
          <small>https с валидным сертификатом. POST‑запрос с JSON‑payload.</small>
        </label>
        <div class="field-label" style="margin-top:4px;font-size:13px;">События</div>
        <div style="padding:10px 12px;border:1px solid var(--color-border-muted);border-radius:10px;background:var(--color-surface-muted);">
          ${eventsHtml}
        </div>
      </form>`;

    MosChatApp.modal({
      title: 'Новый webhook',
      size: 'lg',
      bodyHtml: formHtml,
      submitText: 'Добавить',
      submitClass: 'btn-primary',
      cancelText: 'Отмена',
      onSubmit: async ({ values, close }) => {
        const url = String(values.url || '').trim();
        if (!/^https?:\/\/.+/i.test(url)) throw new Error('Введите валидный URL (https://...)');
        const picked = [];
        document.querySelectorAll('input[name="events"]:checked').forEach((el) => picked.push(el.value));
        if (!picked.length) throw new Error('Выберите хотя бы одно событие');
        await MosChatApp.api('/api/internal/webhooks', {
          method: 'POST',
          body: { url, events: picked },
        });
        MosChatApp.toast('Webhook добавлен', 'success');
        close();
        setTimeout(() => location.reload(), 600);
      },
    });
  });
})();
