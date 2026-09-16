<?php
$step = (int) ($step ?? 1);
$appUrl = $appUrl ?? 'https://moschat.online';
?>
<div class="onboarding">
  <div class="onboarding-progress" role="progressbar" aria-valuenow="<?= $step ?>" aria-valuemin="1" aria-valuemax="4">
    <?php for ($i = 1; $i <= 4; $i++): ?>
      <span class="ob-step <?= $i <= $step ? 'is-done' : '' ?>"><?= $i ?></span>
    <?php endfor; ?>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <?php if ($step === 1): ?>
    <div class="auth-card">
      <h1 class="auth-title">Создайте компанию</h1>
      <p class="auth-sub">Рабочее пространство для команды и чата на сайте</p>
      <form method="post" action="/onboarding/company" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <label class="field"><span>Название компании</span><input class="input" name="name" required></label>
        <label class="field"><span>Сайт</span><input class="input" name="website" placeholder="https://example.com"></label>
        <label class="field"><span>Часовой пояс</span>
          <select class="input" name="timezone">
            <option value="Europe/Moscow">Europe/Moscow</option>
            <option value="UTC">UTC</option>
            <option value="Europe/Samara">Europe/Samara</option>
          </select>
        </label>
        <label class="field"><span>Язык</span>
          <select class="input" name="locale">
            <option value="ru">Русский</option>
            <option value="en">English</option>
          </select>
        </label>
        <button class="btn btn-primary btn-block" type="submit">Продолжить</button>
      </form>
    </div>
  <?php elseif ($step === 2): ?>
    <div class="auth-card">
      <h1 class="auth-title">Настройте чат</h1>
      <p class="auth-sub">Как виджет будет выглядеть на сайте</p>
      <form method="post" action="/onboarding/chat" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <label class="field"><span>Название в чате</span><input class="input" name="company_display_name" value="<?= e($company['name'] ?? '') ?>" required></label>
        <label class="field"><span>Приветствие</span><textarea class="input" name="welcome_message" rows="3">Здравствуйте! Чем можем помочь?</textarea></label>
        <label class="field"><span>Цвет</span><input class="input" type="color" name="primary_color" value="#2563EB"></label>
        <label class="field"><span>Положение</span>
          <select class="input" name="position">
            <option value="bottom-right">Справа внизу</option>
            <option value="bottom-left">Слева внизу</option>
          </select>
        </label>
        <button class="btn btn-primary btn-block" type="submit">Продолжить</button>
      </form>
    </div>
  <?php elseif ($step === 3): ?>
    <div class="auth-card">
      <h1 class="auth-title">Установите код</h1>
      <p class="auth-sub">Вставьте этот фрагмент перед &lt;/body&gt; на сайте</p>
      <?php $pk = $site['public_key'] ?? 'pk_…'; ?>
      <pre class="code-block" id="widget-snippet">&lt;script src="<?= e($appUrl) ?>/widget.js" data-site="<?= e($pk) ?>"&gt;&lt;/script&gt;</pre>
      <button type="button" class="btn btn-primary btn-block" data-copy="#widget-snippet">Скопировать код</button>
      <form method="post" action="/onboarding/install" class="stack" style="margin-top:12px">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <button class="btn btn-ghost btn-block" type="submit">Далее</button>
      </form>
    </div>
  <?php else: ?>
    <div class="auth-card">
      <h1 class="auth-title">Добавьте сотрудников</h1>
      <p class="auth-sub">Можно пропустить и пригласить позже в разделе «Сотрудники»</p>
      <form method="post" action="/onboarding/complete" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <button class="btn btn-primary btn-block" type="submit">Перейти в Inbox</button>
      </form>
    </div>
  <?php endif; ?>
</div>
