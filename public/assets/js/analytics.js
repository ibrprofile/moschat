(() => {
  const fill = (k, v) => {
    const el = document.querySelector(`[data-k="${k}"]`);
    if (el) el.textContent = v;
  };
  MosChatApp.api('/api/internal/analytics/summary').then((res) => {
    const d = res.data || {};
    fill('conversations', d.conversations ?? 0);
    fill('clients', d.clients ?? 0);
    fill('closed', d.closed ?? 0);
    fill('avg_response', d.avg_response_label || d.avg_response || '—');
    const canvas = document.getElementById('analytics-chart');
    if (canvas && window.Chart) {
      new Chart(canvas, {
        type: 'line',
        data: {
          labels: d.series?.labels || [],
          datasets: [{
            label: 'Диалоги',
            data: d.series?.values || [],
            borderColor: '#2563EB',
            backgroundColor: 'rgba(37,99,235,.12)',
            tension: 0.3,
            fill: true,
          }],
        },
        options: {
          plugins: { legend: { display: false } },
          scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
      });
    }
  }).catch(() => {});
})();
