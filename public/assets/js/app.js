(() => {
  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  const ICONS = {
    check: '<svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
    alertCircle: '<svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>',
    alertTriangle: '<svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>',
    info: '<svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="16" y2="12"/><line x1="12" x2="12.01" y1="8" y2="8"/></svg>',
    close: '<svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
    mail: '<svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
    shield: '<svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>',
    userPlus: '<svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>',
    search: '<svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
  };

  const typeMeta = {
    success: { accent: 'success', icon: ICONS.check },
    error: { accent: 'error', icon: ICONS.alertCircle },
    warning: { accent: 'warning', icon: ICONS.alertTriangle },
    info: { accent: 'info', icon: ICONS.info },
  };

  window.MosChatApp = {
    csrf,
    esc,
    ICONS,
    async api(path, options = {}) {
      const opts = { ...options };
      opts.headers = {
        Accept: 'application/json',
        'X-CSRF-Token': csrf(),
        ...(opts.headers || {}),
      };
      if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(opts.body);
      }
      const res = await fetch(path, opts);
      let data = null;
      try { data = await res.json(); } catch (_) {}
      if (!res.ok || data?.success === false) {
        const msg = data?.error?.message || `Ошибка ${res.status}`;
        const err = new Error(msg);
        err.payload = data;
        err.status = res.status;
        throw err;
      }
      return data;
    },
    toast(message, type = 'info', options = {}) {
      const root = document.getElementById('toast-root');
      if (!root) return;
      const meta = typeMeta[type] || typeMeta.info;
      const timeout = options.timeout ?? 2800;
      const el = document.createElement('div');
      el.className = `toast ${meta.accent}`;
      el.innerHTML = `
        <span class="toast-icon">${meta.icon}</span>
        <div style="flex:1;min-width:0;"><div style="font-weight:550;">${esc(message)}</div>${options.detail ? `<div style="font-size:12px;color:var(--color-text-secondary);margin-top:2px;">${esc(options.detail)}</div>` : ''}</div>
        <button type="button" class="toast-close" aria-label="Закрыть">${ICONS.close}</button>
      `;
      el.querySelector('.toast-close')?.addEventListener('click', () => {
        el.classList.add('leaving');
        setTimeout(() => el.remove(), 160);
      });
      root.appendChild(el);
      if (timeout > 0) {
        setTimeout(() => {
          el.classList.add('leaving');
          setTimeout(() => el.remove(), 160);
        }, timeout);
      }
      return el;
    },
    copyText(text) {
      return navigator.clipboard.writeText(text);
    },
    modal({ title, bodyHtml, bodyEl, footerHtml, footerEl, size = 'md', onSubmit, submitText = 'Сохранить', cancelText = 'Отмена', showCancel = true, submitClass = 'btn-primary' } = {}) {
      const root = document.getElementById('modal-root');
      if (!root) return null;
      const backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop';
      const modal = document.createElement('div');
      modal.className = 'modal';
      if (size === 'lg') modal.style.maxWidth = '640px';
      if (size === 'sm') modal.style.maxWidth = '400px';
      modal.innerHTML = `
        <div class="modal-head">
          <h3>${esc(title || '')}</h3>
          <button type="button" class="sidebar-close modal-close" aria-label="Закрыть">${ICONS.close}</button>
        </div>
        <div class="modal-body"></div>
        <div class="modal-foot">
          ${showCancel ? `<button type="button" class="btn btn-ghost modal-cancel">${esc(cancelText)}</button>` : ''}
          <button type="button" class="btn ${submitClass} modal-submit">${esc(submitText)}</button>
        </div>
      `;
      backdrop.appendChild(modal);
      root.appendChild(backdrop);
      root.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';

      const bodyNode = modal.querySelector('.modal-body');
      if (bodyEl) bodyNode.appendChild(bodyEl);
      else if (bodyHtml) bodyNode.innerHTML = bodyHtml;

      const footerNode = modal.querySelector('.modal-foot');
      if (footerEl) footerNode.appendChild(footerEl);
      else if (footerHtml) footerNode.insertAdjacentHTML('beforeend', footerHtml);

      const close = (result) => {
        backdrop.style.animation = 'fade-in 120ms var(--ease-in-out) reverse forwards';
        modal.style.animation = 'modal-in 120ms var(--ease-in-out) reverse forwards';
        setTimeout(() => {
          backdrop.remove();
          if (!root.children.length) root.setAttribute('aria-hidden', 'true');
          document.body.style.overflow = '';
        }, 130);
      };

      const submitBtn = modal.querySelector('.modal-submit');
      const cancelBtn = modal.querySelector('.modal-cancel');
      backdrop.addEventListener('click', (e) => { if (e.target === backdrop) close(); });
      modal.querySelector('.modal-close')?.addEventListener('click', () => close());
      cancelBtn?.addEventListener('click', () => close());

      if (onSubmit) {
        submitBtn?.addEventListener('click', async () => {
          const form = bodyNode.querySelector('form');
          const getValues = () => {
            if (!form) return {};
            const fd = new FormData(form);
            const out = {};
            fd.forEach((v, k) => {
              if (out[k] !== undefined) return;
              const el = form.elements.namedItem(k);
              if (el && el.type === 'checkbox') out[k] = el.checked ? 1 : 0;
              else out[k] = v;
            });
            form.querySelectorAll('input[type=checkbox]').forEach((el) => {
              if (!fd.has(el.name)) out[el.name] = 0;
            });
            return out;
          };
          try {
            submitBtn.disabled = true;
            const res = await onSubmit({ close, body: bodyNode, values: getValues(), form });
            if (res !== false) close(res);
          } catch (err) {
            MosChatApp.toast(err.message, 'error');
          } finally {
            submitBtn.disabled = false;
          }
        });
      } else {
        submitBtn?.addEventListener('click', () => close(true));
      }

      modal.querySelectorAll('input, select, textarea, button')?.[0]?.focus?.();
      return { backdrop, modal, close };
    },
  };

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-copy]');
    if (!btn) return;
    const sel = btn.getAttribute('data-copy');
    const node = document.querySelector(sel);
    if (!node) return;
    const text = node.innerText || node.textContent || '';
    MosChatApp.copyText(text)
      .then(() => MosChatApp.toast('Скопировано в буфер', 'success'))
      .catch(() => MosChatApp.toast('Не удалось скопировать', 'error'));
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const backdrops = document.querySelectorAll('#modal-root .modal-backdrop');
      if (backdrops.length) {
        const last = backdrops[backdrops.length - 1];
        last.querySelector('.modal-close')?.click();
      }
    }
  });

  const shell = document.getElementById('app-shell');
  const openBtn = document.getElementById('sidebar-open');
  const closeBtn = document.getElementById('sidebar-close');
  const collapseBtn = document.getElementById('sidebar-collapse');
  const backdrop = document.getElementById('sidebar-backdrop');

  const applyCollapsed = (on) => {
    shell?.classList.toggle('sidebar-collapsed', !!on);
    try { localStorage.setItem('sidebar:collapsed', on ? '1' : '0'); } catch (_) {}
  };
  const applyOpen = (on) => {
    shell?.classList.toggle('sidebar-open', !!on);
    backdrop?.toggleAttribute('hidden', !on);
  };

  try {
    if (localStorage.getItem('sidebar:collapsed') === '1') applyCollapsed(true);
  } catch (_) {}

  openBtn?.addEventListener('click', () => applyOpen(true));
  closeBtn?.addEventListener('click', () => applyOpen(false));
  backdrop?.addEventListener('click', () => applyOpen(false));
  collapseBtn?.addEventListener('click', () => {
    applyCollapsed(!shell?.classList.contains('sidebar-collapsed'));
  });

  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        const el = entry.target;
        const delay = el.style.getPropertyValue('--d') || '0ms';
        el.style.animationDelay = delay;
        el.classList.add('reveal-in');
        io.unobserve(el);
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -20px 0px' });
    const scan = () => document.querySelectorAll('.reveal:not(.reveal-in)').forEach((el) => io.observe(el));
    scan();
    window.MosChatApp.revealScan = scan;
    document.addEventListener('DOMContentLoaded', scan);
  } else {
    document.querySelectorAll('.reveal').forEach((el) => el.classList.add('reveal-in'));
    window.MosChatApp.revealScan = () => {};
  }

  document.querySelectorAll('.reveal-instant').forEach((el) => {
    const delay = el.style.getPropertyValue('--d') || '0ms';
    el.style.animationDelay = delay;
    el.classList.add('reveal-in');
  });
})();
