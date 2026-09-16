<?php require __DIR__ . '/_header.php';
$ws = $widgetSettings ?? [];
$pk = $site['public_key'] ?? '';
$appUrl = $appUrl ?? 'https://moschat.online';

function boolCk($v) { return !empty($v) ? '1' : '0'; }

$color = e($ws['primary_color'] ?? '#2563EB');
$name  = e($ws['company_display_name'] ?? 'MosChat');
$welcome = e($ws['welcome_message'] ?? 'Здравствуйте! Чем можем помочь?');
$offline = e($ws['offline_message'] ?? 'Мы не в сети сейчас. Оставьте контакты — ответим в рабочее время.');
$position = e($ws['position'] ?? 'bottom-right');
?>
<h2 class="section-title">Виджет</h2>
<div class="settings-split">
  <form class="stack" data-api="/api/internal/settings/widget" id="widget-form">
    <div class="card-floating" style="padding:14px 16px;margin-bottom:8px;">
      <div class="row-between" style="gap:12px;">
        <div>
          <div class="text-primary" style="font-weight:600;">Внешний вид</div>
          <div class="text-secondary" style="font-size:13px;margin-top:2px;">Имя, приветствие, цвет и позиция виджета на сайте</div>
        </div>
        <span class="badge badge-info" id="preview-badge">Живое превью</span>
      </div>
    </div>

    <label class="field">
      <span>Имя оператора</span>
      <input class="input" name="company_display_name" id="s-name" value="<?= $name ?>" maxlength="60">
      <small>Отображается в шапке окна чата</small>
    </label>

    <div class="field-row">
      <label class="field" style="flex:1 1 120px;">
        <span>Цвет акцента</span>
        <div class="input color-picker-input">
          <input type="color" id="s-color" name="primary_color" value="<?= $color ?>">
          <span id="s-color-hex" style="font-variant-numeric:tabular-nums;"><?= e(strtoupper($color)) ?></span>
        </div>
      </label>
      <label class="field" style="flex:1 1 120px;">
        <span>Положение</span>
        <select class="input" name="position" id="s-position">
          <option value="bottom-right" <?= $position === 'bottom-right' ? 'selected' : '' ?>>Справа снизу</option>
          <option value="bottom-left"  <?= $position === 'bottom-left'  ? 'selected' : '' ?>>Слева снизу</option>
        </select>
      </label>
    </div>

    <label class="field">
      <span>Приветственное сообщение</span>
      <textarea class="input" id="s-welcome" name="welcome_message" rows="3" maxlength="240"><?= $welcome ?></textarea>
      <small>Показывается первым при открытии чата</small>
    </label>

    <label class="field">
      <span>Офлайн-сообщение</span>
      <textarea class="input" id="s-offline" name="offline_message" rows="2" maxlength="240"><?= $offline ?></textarea>
      <small>Когда операторы не в сети</small>
    </label>

    <div class="card-floating" style="padding:14px 16px;margin:8px 0;">
      <div style="font-weight:600;">Сбор контактов</div>
      <div class="text-secondary" style="font-size:13px;margin-top:2px;">Будем запрашивать эти данные перед первым сообщением</div>
    </div>

    <label class="check"><input type="checkbox" id="s-name-req" name="collect_name" value="1" <?= !empty($ws['collect_name']) ? 'checked' : '' ?>> Спрашивать имя</label>
    <label class="check"><input type="checkbox" id="s-email-req" name="collect_email" value="1" <?= !empty($ws['collect_email']) ? 'checked' : '' ?>> Спрашивать email</label>
    <label class="check"><input type="checkbox" id="s-phone-req" name="collect_phone" value="1" <?= !empty($ws['collect_phone']) ? 'checked' : '' ?>> Спрашивать телефон</label>
    <label class="check"><input type="checkbox" id="s-privacy" name="privacy_consent_required" value="1" <?= !empty($ws['privacy_consent_required']) ? 'checked' : '' ?>> Обязательное согласие на обработку данных</label>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit">
        <svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        Сохранить
      </button>
      <button type="button" class="btn btn-ghost" id="widget-reset">Сбросить</button>
    </div>
  </form>

  <div class="settings-preview">
    <div class="row-between" style="margin-bottom:10px;">
      <h3 class="section-title" style="margin:0;">Как выглядит на сайте</h3>
      <span class="badge badge-neutral">iPhone · 360×750</span>
    </div>

    <div class="device-frame reveal" style="--d:40ms;">
      <div class="device-notch"></div>
      <div class="device-screen">
        <div class="device-statusbar">
          <span class="sb-time">9:41</span>
          <span class="sb-symbols">
            <svg viewBox="0 0 18 12" class="sb-sig" fill="currentColor"><rect x="0" y="9" width="3" height="3"/><rect x="5" y="6" width="3" height="6"/><rect x="10" y="3" width="3" height="9"/><rect x="15" y="0" width="3" height="12"/></svg>
            <svg viewBox="0 0 16 12" class="sb-wifi" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M8 11v.01"/><path d="M4 9c1.5-1 2.5-1.5 4-1.5s2.5.5 4 1.5"/><path d="M.5 6.5c2.5-2.5 5.5-4 7.5-4s5 1.5 7.5 4"/><path d="M-3 4c3-4 6-6 11-6s8 2 11 6"/></svg>
            <svg viewBox="0 0 24 12" class="sb-batt" fill="none" stroke="currentColor" stroke-width="1.3"><rect x="0.5" y="0.5" width="20" height="11" rx="3"/><rect x="2.5" y="2.5" width="15" height="7" rx="1.5" fill="currentColor"/><rect x="21" y="4" width="2.5" height="4" rx="1" fill="currentColor"/></svg>
          </span>
        </div>

        <div class="device-page">
          <div class="device-site">
            <div class="ds-nav">
              <span class="ds-logo-dot"></span>
              <span class="ds-logo-text">VECTOR.RU</span>
              <span class="ds-nav-link">Каталог</span>
              <span class="ds-nav-link">Услуги</span>
              <span class="ds-nav-link">Контакты</span>
            </div>
            <div class="ds-hero">
              <div class="ds-hero-title">ИТ‑решения для бизнеса</div>
              <div class="ds-hero-sub">Внедрение CRM, поддержка 24/7, SLA 99.9%</div>
              <div class="ds-cta">Оставить заявку</div>
            </div>
            <div class="ds-card ds-card-1">
              <div class="ds-skeleton-line" style="width:70%;"></div>
              <div class="ds-skeleton-line" style="width:95%;height:6px;margin-top:8px;"></div>
              <div class="ds-skeleton-line" style="width:80%;height:6px;margin-top:5px;"></div>
              <div class="ds-mini-chip" style="margin-top:12px;"># 12 400 ₽ / мес</div>
            </div>
            <div class="ds-card ds-card-2">
              <div class="ds-skeleton-line" style="width:55%;"></div>
              <div class="ds-skeleton-line" style="width:100%;height:6px;margin-top:8px;"></div>
              <div class="ds-skeleton-line" style="width:65%;height:6px;margin-top:5px;"></div>
              <div class="ds-mini-chip" style="margin-top:12px;"># 26 800 ₽ / мес</div>
            </div>
          </div>
        </div>

        <div class="widget-stage" id="widget-stage" data-position="<?= $position ?>" data-color="<?= $color ?>">
          <button class="bubble-fab" id="bubble-fab" type="button" aria-label="Открыть чат" title="Открыть чат">
            <svg class="bf-icon bf-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            <svg class="bf-icon bf-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
          </button>

          <div class="chat-window" id="chat-window">
            <div class="chat-window-head" id="chat-window-head">
              <div class="cwh-left">
                <span class="avatar avatar-sm avatar-presence online" id="cw-avatar">MC</span>
                <div class="cwh-titles">
                  <span class="cwh-name" id="cw-name">MosChat</span>
                  <span class="cwh-status"><span class="badge-dot"></span>Обычно отвечаем за 30 секунд</span>
                </div>
              </div>
              <button type="button" class="btn-icon-ghost cw-min" id="cw-min" title="Свернуть" aria-label="Свернуть">
                <svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
              </button>
            </div>

            <div class="chat-window-body" id="chat-window-body">
              <div class="cw-day-sep">Сегодня</div>
              <div class="msg client">
                <div class="msg-meta"><span id="cw-welcome-from">Алексей, клиент VECTOR.RU</span><span class="msg-time">9:32</span></div>
                <div>Здравствуйте, подскажите — планируете запустить на нашем сайте.</div>
              </div>
              <div class="msg agent" id="cw-welcome-msg">
                <div class="msg-meta"><span id="cw-agent-name">MosChat</span><span class="msg-time">9:32</span></div>
                <div id="cw-welcome-body">Здравствуйте! Чем можем помочь?</div>
              </div>
              <div class="msg client" id="cw-offline-group" style="display:none;">
                <div class="msg-meta"><span>Клиент</span><span class="msg-time">9:33</span></div>
                <div>Хорошо, я напишу. На связи.</div>
              </div>
              <div class="msg agent cw-offline-box" id="cw-offline-box" style="display:none;">
                <div class="msg-meta"><span id="cw-offline-name">MosChat</span><span class="msg-time">9:33</span></div>
                <div id="cw-offline-body">Мы не в сети сейчас. Оставьте контакты — ответим в рабочее время.</div>
              </div>
              <div class="typing-indicator" id="cw-typing"><span></span><span></span><span></span></div>
            </div>

            <div class="chat-window-form" id="chat-window-form">
              <div class="cw-input-group">
                <input class="cw-input" type="text" placeholder="Ваше сообщение…" readonly>
                <button class="cw-send" type="button" aria-label="Отправить">
                  <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.4 20.4 21 12 3.4 3.6 3 10l12 2-12 2z"/></svg>
                </button>
              </div>
              <div class="cw-consent" id="cw-consent" style="display:none;">
                <label class="check compact"><input type="checkbox" checked disabled> <span>Согласен(на) на обработку персональных данных</span></label>
              </div>
              <div class="cw-reqs" id="cw-reqs" style="display:none;">
                <input class="cw-mini-input" id="cw-req-name" placeholder="Ваше имя" readonly>
                <input class="cw-mini-input" id="cw-req-email" type="email" placeholder="Email" readonly>
                <input class="cw-mini-input" id="cw-req-phone" type="tel" placeholder="Телефон" readonly>
              </div>
            </div>
          </div>

          <div class="bubble-pulse"></div>
          <div class="bubble-pulse-delay"></div>
        </div>

        <div class="device-homebar"></div>
      </div>
    </div>

    <div class="card-floating reveal" style="--d:80ms;margin-top:14px;">
      <div class="row-between" style="margin-bottom:10px;">
        <div style="font-weight:600;">Код установки</div>
        <button type="button" class="btn btn-ghost btn-sm" data-copy="#install-code">
          <svg class="icon icon-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
          Скопировать
        </button>
      </div>
      <pre class="code-block" id="install-code" style="margin:0;">&lt;script src="<?= e($appUrl) ?>/widget.js" data-site="<?= e($pk) ?>" defer&gt;&lt;/script&gt;</pre>
      <div class="text-secondary" style="margin-top:8px;font-size:12px;">Вставьте перед закрывающим тегом &lt;/body&gt; на каждой странице сайта</div>
    </div>
  </div>
</div>
<script src="/assets/js/settings.js" defer></script>
<?php require __DIR__ . '/_footer.php'; ?>
