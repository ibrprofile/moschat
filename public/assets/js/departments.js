(() => {
  const list = document.getElementById('departments-list');
  if (!list) return;
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const load = async () => {
    const data = await MosChatApp.api('/api/internal/departments');
    const items = data.data?.items || data.data || [];
    if (!items.length) return;
    list.className = 'stack';
    list.innerHTML = items.map((d) => `<div class="stat"><strong>${esc(d.name)}</strong><div class="text-meta">${esc(d.description || '')}</div></div>`).join('');
  };
  document.getElementById('dept-create')?.addEventListener('click', async () => {
    const name = prompt('Название отдела');
    if (!name) return;
    try {
      await MosChatApp.api('/api/internal/departments', { method: 'POST', body: { name } });
      MosChatApp.toast('Отдел создан');
      await load();
    } catch (err) {
      MosChatApp.toast(err.message);
    }
  });
  load().catch((e) => MosChatApp.toast(e.message));
})();
