<div class="auth-card">
  <h1 class="auth-title">Новый пароль</h1>
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>
  <form method="post" action="/reset-password" class="stack">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label class="field">
      <span>Новый пароль</span>
      <input class="input" type="password" name="password" required minlength="8">
    </label>
    <label class="field">
      <span>Повтор пароля</span>
      <input class="input" type="password" name="password_confirmation" required minlength="8">
    </label>
    <button class="btn btn-primary btn-block" type="submit">Сохранить</button>
  </form>
</div>
