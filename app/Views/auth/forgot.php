<div class="auth-card">
  <h1 class="auth-title">Восстановление пароля</h1>
  <?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>
  <form method="post" action="/forgot-password" class="stack">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label class="field">
      <span>Email</span>
      <input class="input" type="email" name="email" required>
    </label>
    <button class="btn btn-primary btn-block" type="submit">Отправить ссылку</button>
  </form>
  <p class="auth-links"><a href="/login">Назад ко входу</a></p>
</div>
