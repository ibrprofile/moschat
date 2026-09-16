<?php $appUrl = rtrim((string) config('app.url'), '/');
$publicKey = $site['public_key'] ?? 'pk_live_xxxxxxxxxxxx';
?>
<div class="docs-page">
  <aside class="docs-side reveal" style="--d:20ms;">
    <h3>Документация</h3>
    <ul class="docs-nav" id="docs-nav">
      <li><a href="#start" class="is-active">Начало работы</a></li>
      <li><a href="#install">Установка виджета</a></li>
      <li><a href="#auth">Авторизация</a></li>
      <li><a href="#widget">Виджет чата</a></li>
      <li><a href="#jsapi">JavaScript API</a></li>
      <li><a href="#rest">REST API</a></li>
      <li><a href="#webhooks">Webhooks</a></li>
      <li><a href="#errors">Коды ошибок</a></li>
    </ul>
  </aside>

  <article class="docs-content">
    <section class="doc-section reveal" style="--d:40ms;" id="start">
      <h2>Начало работы</h2>
      <p>MosChat — платформа онлайн‑чатов и CRM для сайтов. Виджет устанавливается одной строкой кода и не требует изменений в существующей вёрстке. Все диалоги, клиенты и метрики доступны в личном кабинете.</p>
      <div class="card-floating" style="padding:12px 16px;margin:10px 0 6px;">
        <div class="row" style="gap:10px;align-items:flex-start;">
          <span class="badge badge-success"><span class="badge-dot"></span>Быстрый старт</span>
          <div>
            <div style="font-weight:600;">3 простых шага</div>
            <div class="text-secondary" style="font-size:13px;margin-top:2px;">Скопируйте код → вставьте в &lt;/body&gt; → настройте приветствие в разделе Настройки → Виджет.</div>
          </div>
        </div>
      </div>
    </section>

    <section class="doc-section reveal" style="--d:60ms;" id="install">
      <h2>Установка виджета</h2>
      <p>Скопируйте этот блок в конец страницы перед тегом <code>&lt;/body&gt;</code>. Вместо <code>YOUR_PUBLIC_KEY</code> подставьте ключ из раздела Настройки → Сайт.</p>
      <pre class="code-block" id="install-snippet">&lt;script src="<?= e($appUrl) ?>/widget.js"
        data-site="<?= e($publicKey) ?>"
        defer&gt;&lt;/script&gt;</pre>
      <div class="row" style="gap:8px; margin-top:8px;">
        <button class="btn btn-ghost btn-sm" type="button" data-copy="#install-snippet">
          <svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
          Скопировать код
        </button>
        <span class="badge badge-neutral">поддержка: Chrome · Safari · Firefox · Edge</span>
      </div>
      <p style="margin-top:16px;">Виджет загружается асинхронно (атрибут <code>defer</code>) и не блокирует отрисовку страницы. Размер gzip ≈ 17&nbsp;KB.</p>
    </section>

    <section class="doc-section reveal" style="--d:80ms;" id="auth">
      <h2>Авторизация</h2>
      <p>Для запросов к REST API используйте Bearer‑токен. Секретные токены создаются в разделе Настройки → API и показываются один раз при создании.</p>
      <div class="endpoint"><span class="method get">Заголовок</span><span class="path">Authorization: Bearer sk_live_********************************</span></div>
      <p>Внутренние запросы из панели управления используют CSRF‑токен из заголовка <code>X-CSRF-Token</code>. Сессия хранится в httpOnly cookie.</p>
    </section>

    <section class="doc-section reveal" style="--d:100ms;" id="widget">
      <h2>Виджет чата</h2>
      <p>Виджет изолирован внутри Shadow DOM, не конфликтует с existing‑стилями сайта и не переопределяет глобальные классы. Поддерживает кастомизацию: цвет, имя оператора, текст приветствия, офлайн‑сообщение, положение, обязательные поля, согласие на обработку персональных данных.</p>
      <p>Настройки применяются из раздела Настройки → Виджет без изменения кода на сайте — конфигурация подтягивается один раз при загрузке скрипта.</p>
    </section>

    <section class="doc-section reveal" style="--d:120ms;" id="jsapi">
      <h2>JavaScript API</h2>
      <p>Глобальный объект <code>MosChat</code> доступен сразу после загрузки виджета. Позволяет программно открывать/закрывать окно чата, передавать данные клиента и подписываться на события.</p>
      <pre class="code-block">// открыть окно чата
MosChat.open();

// свернуть окно
MosChat.close();

// передать данные посетителя (имя, email, телефон)
MosChat.setUser({
  name:  "Иван Петров",
  email: "ivan@vector.ru",
  phone: "+7 (495) 000-00-00",
  custom_fields: { company: "ООО Вектор" }
});

// подписка на события
MosChat.on("ready",   () => console.log("Виджет готов"));
MosChat.on("open",    () => console.log("Чат открыт"));
MosChat.on("close",   () => console.log("Чат закрыт"));
MosChat.on("message", (msg) => console.log("Новое:", msg));</pre>
    </section>

    <section class="doc-section reveal" style="--d:140ms;" id="rest">
      <h2>REST API</h2>
      <p>Базовый адрес: <code><?= e($appUrl) ?>/api/v1</code>. Все тела запросов и ответов — JSON. Таймаут сервера: 30&nbsp;секунд.</p>

      <div class="stack" style="gap:8px;">
        <h3 style="font-size:14px;margin:16px 0 4px;">Клиенты</h3>
        <div class="endpoint"><span class="method get">GET</span><span class="path">/api/v1/clients</span><span class="endpoint-desc">Список клиентов с фильтрами q, status, tag, page, limit</span></div>
        <div class="endpoint"><span class="method post">POST</span><span class="path">/api/v1/clients</span><span class="endpoint-desc">Создать нового клиента (name, email, phone, company, tag)</span></div>
        <div class="endpoint"><span class="method get">GET</span><span class="path">/api/v1/clients/:id</span><span class="endpoint-desc">Получить профиль клиента со статистикой</span></div>
        <div class="endpoint"><span class="method patch">PATCH</span><span class="path">/api/v1/clients/:id</span><span class="endpoint-desc">Обновить данные клиента, статус или сделку</span></div>
        <div class="endpoint"><span class="method delete">DELETE</span><span class="path">/api/v1/clients/:id</span><span class="endpoint-desc">Пометить клиента как архивный</span></div>

        <h3 style="font-size:14px;margin:24px 0 4px;">Диалоги и сообщения</h3>
        <div class="endpoint"><span class="method get">GET</span><span class="path">/api/v1/conversations</span><span class="endpoint-desc">Список диалогов (status=active|pending|closed)</span></div>
        <div class="endpoint"><span class="method get">GET</span><span class="path">/api/v1/conversations/:id/messages</span><span class="endpoint-desc">Лента сообщений в диалоге</span></div>
        <div class="endpoint"><span class="method post">POST</span><span class="path">/api/v1/conversations/:id/messages</span><span class="endpoint-desc">Отправить сообщение от имени оператора</span></div>
        <div class="endpoint"><span class="method patch">PATCH</span><span class="path">/api/v1/conversations/:id</span><span class="endpoint-desc">Закрыть диалог или назначить ответственного</span></div>
      </div>

      <h3 style="font-size:14px;margin:24px 0 8px;">Пример запроса</h3>
      <pre class="code-block">curl -sS <?= e($appUrl) ?>/api/v1/clients?limit=50 \
  -H "Authorization: Bearer sk_live_xxxxxxxx" \
  -H "Accept: application/json"</pre>
    </section>

    <section class="doc-section reveal" style="--d:160ms;" id="webhooks">
      <h2>Webhooks</h2>
      <p>Подписка на события в реальном времени. URL должен возвращать 2xx‑ответ за 5&nbsp;секунд. Webhooks подписываются заголовком <code>X-MosChat-Signature</code> (HMAC‑SHA256, секрет из настроек).</p>
      <div class="card-floating" style="padding:12px 16px;margin:10px 0;">
        <div style="font-weight:600;margin-bottom:4px;">Доступные события</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
          <span class="chip chip-accent-outline">message.created</span>
          <span class="chip chip-accent-outline">conversation.created</span>
          <span class="chip chip-accent-outline">conversation.closed</span>
          <span class="chip chip-accent-outline">conversation.assigned</span>
          <span class="chip chip-accent-outline">client.created</span>
          <span class="chip chip-accent-outline">client.updated</span>
          <span class="chip chip-accent-outline">client.tagged</span>
        </div>
      </div>
      <h3 style="font-size:14px;margin:18px 0 6px;">Пример проверки подписи (PHP)</h3>
      <pre class="code-block">function verifySignature(string $body, string $signature, string $secret): bool
{
    $expected = hash_hmac('sha256', $body, $secret);
    return hash_equals($expected, $signature);
}</pre>
    </section>

    <section class="doc-section reveal" style="--d:180ms;" id="errors">
      <h2>Коды ошибок</h2>
      <p>Все ошибки возвращают единый формат ответа с полями <code>success</code>, <code>error.code</code> и <code>error.message</code>.</p>
      <pre class="code-block">{
  "success": false,
  "error": {
    "code":    "VALIDATION_ERROR",
    "message": "Некорректный email",
    "field":   "email"
  }
}</pre>
      <div class="table-wrap" style="margin-top:14px;">
        <table class="table">
          <thead><tr>
            <th style="width:110px;">HTTP</th>
            <th style="width:220px;">Код</th>
            <th>Описание</th>
          </tr></thead>
          <tbody>
            <tr><td><span class="badge badge-success">200</span></td><td><code>OK</code></td><td>Запрос выполнен успешно</td></tr>
            <tr><td><span class="badge badge-warning">400</span></td><td><code>VALIDATION_ERROR</code></td><td>Неверный формат полей или пропущенные обязательные</td></tr>
            <tr><td><span class="badge badge-danger">401</span></td><td><code>UNAUTHORIZED</code></td><td>Пропущен или невалидный Bearer‑токен</td></tr>
            <tr><td><span class="badge badge-danger">403</span></td><td><code>PERMISSION_DENIED</code></td><td>Роль или scope токена не позволяют выполнить операцию</td></tr>
            <tr><td><span class="badge badge-neutral">404</span></td><td><code>NOT_FOUND</code></td><td>Сущность не найдена по ID</td></tr>
            <tr><td><span class="badge badge-warning">429</span></td><td><code>RATE_LIMITED</code></td><td>Превышен лимит запросов (600/minute по умолчанию)</td></tr>
            <tr><td><span class="badge badge-danger">500</span></td><td><code>INTERNAL_ERROR</code></td><td>Серверная ошибка — повторите позже</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </article>
</div>

<script>
(() => {
  const nav = document.getElementById('docs-nav');
  if (!nav) return;
  const links = Array.from(nav.querySelectorAll('a[href^="#"]'));
  const sections = links
    .map((a) => document.getElementById(a.getAttribute('href').slice(1)))
    .filter(Boolean);
  if (!sections.length) return;
  const setActive = (id) => {
    links.forEach((l) => {
      l.classList.toggle('is-active', l.getAttribute('href') === '#' + id);
    });
  };
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      const visible = entries
        .filter((e) => e.isIntersecting)
        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
      if (visible?.target?.id) setActive(visible.target.id);
    }, { rootMargin: '-100px 0px -65% 0px', threshold: [0, 0.1, 0.25, 0.5, 0.8, 1] });
    sections.forEach((s) => io.observe(s));
  }
  links.forEach((a) => {
    a.addEventListener('click', (e) => {
      const id = a.getAttribute('href').slice(1);
      const target = document.getElementById(id);
      if (!target) return;
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      if (history.replaceState) history.replaceState(null, '', '#' + id);
      setActive(id);
    });
  });
})();
</script>
