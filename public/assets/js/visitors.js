(() => {
  const list = document.getElementById('visitors-list');
  if (!list) return;
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  MosChatApp.api('/api/internal/visitors').then((data) => {
    const items = data.data?.items || data.data || [];
    if (!items.length) return;
    list.innerHTML = items.map((v) => `<div class="stat" style="display:flex;justify-content:space-between;align-items:center;gap:12px">
      <div><strong>${esc(v.browser || 'Visitor')}</strong><div class="text-meta">${esc(v.current_url || v.landing_page || '')}</div></div>
      <button class="btn btn-primary btn-sm" data-start="${v.id}">Начать чат</button>
    </div>`).join('');
    list.querySelectorAll('[data-start]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        try {
          const res = await MosChatApp.api('/api/internal/visitors/' + btn.dataset.start + '/start-chat', { method: 'POST', body: {} });
          location.href = '/app/inbox/' + (res.data?.id || res.data?.conversation_id);
        } catch (err) {
          MosChatApp.toast(err.message);
        }
      });
    });
  }).catch(() => {});
})();
