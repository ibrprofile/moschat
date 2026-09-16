<?php
/** @var string $title */
/** @var string|null $error */
/** @var string|null $message */
/** @var string|null $dev_code */
/** @var string $csrf */
/** @var string $email */
?>
<div class="auth-body">
  <div class="auth-shell">
    <a class="auth-brand" href="/">MosChat</a>
    <div class="auth-card">
      <h1 class="auth-title">Подтвердите email</h1>
      <p class="auth-sub">Мы отправили 6-значный код на <strong><?= e($email) ?></strong></p>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
      <?php if ($dev_code): ?>
        <div class="alert alert-success" style="margin-bottom:14px;">
          Dev-код (отображается, пока не настроен SMTP): <strong><?= e($dev_code) ?></strong>
        </div>
      <?php endif; ?>
      <form method="post" action="/verify-email" class="stack">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <label class="field">
          <span>Код подтверждения</span>
          <input class="input" name="code" inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="one-time-code" required>
        </label>
        <button type="submit" class="btn btn-primary btn-block">Подтвердить</button>
      </form>
      <div class="auth-links">
        <form method="post" action="/verify-email/resend" style="display:inline;">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button type="submit" style="background:none;border:0;padding:0;color:var(--color-primary);cursor:pointer;font-size:var(--text-meta);">Отправить код повторно</button>
        </form>
        <form method="post" action="/logout" style="display:inline;">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <button type="submit" style="background:none;border:0;padding:0;color:var(--color-text-secondary);cursor:pointer;font-size:var(--text-meta);">Выйти</button>
        </form>
      </div>
    </div>
  </div>
</div>
