<?php
/** @var string $csrf */
/** @var string|null $error */
/** @var string $invite */
?>
<div class="auth-card">
  <h1 class="auth-title"><?= $invite !== '' ? 'Присоединение к команде' : 'Регистрация' ?></h1>
  <p class="auth-sub"><?= $invite !== '' ? 'Завершите регистрацию, чтобы начать работать.' : 'Создайте рабочее пространство MosChat' ?></p>
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
  <?php endif; ?>
  <form method="post" action="/register" class="stack">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <?php if ($invite !== ''): ?><input type="hidden" name="invite" value="<?= e($invite) ?>"><?php endif; ?>
    <label class="field">
      <span>Имя</span>
      <input class="input" type="text" name="name" required autocomplete="name">
    </label>
    <label class="field">
      <span>Email</span>
      <input class="input" type="email" name="email" required autocomplete="email">
    </label>
    <label class="field">
      <span>Пароль</span>
      <input class="input" type="password" name="password" required minlength="8" autocomplete="new-password">
    </label>
    <label class="field">
      <span>Повтор пароля</span>
      <input class="input" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
    </label>
    <button class="btn btn-primary btn-block" type="submit"><?= $invite !== '' ? 'Принять приглашение' : 'Продолжить' ?></button>
  </form>
  <p class="auth-links">
    <a href="/login">Уже есть аккаунт</a>
  </p>
</div>
