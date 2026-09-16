<?php
/** @var string $csrf */
/** @var string|null $error */
/** @var string|null $message */
?>
<div class="auth-card">
  <h1 class="auth-title">Вход в MosChat</h1>
  <p class="auth-sub">Центр общения с клиентами для бизнеса</p>
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
  <?php endif; ?>
  <?php if (!empty($message)): ?>
    <div class="alert alert-success" role="alert"><?= e($message) ?></div>
  <?php endif; ?>
  <form method="post" action="/login" class="stack">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label class="field">
      <span>Email</span>
      <input class="input" type="email" name="email" required autocomplete="username">
    </label>
    <label class="field">
      <span>Пароль</span>
      <input class="input" type="password" name="password" required autocomplete="current-password">
    </label>
    <button class="btn btn-primary btn-block" type="submit">Войти</button>
  </form>
  <p class="auth-links">
    <a href="/forgot-password">Забыли пароль?</a>
    <a href="/register">Создать аккаунт</a>
  </p>
</div>
