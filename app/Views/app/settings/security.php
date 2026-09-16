<?php require __DIR__ . '/_header.php'; ?>
<h2 class="section-title">Безопасность</h2>
<p class="text-secondary">Смена пароля и сессии.</p>
<form class="stack form-narrow" data-api="/api/internal/profile/password">
  <label class="field"><span>Текущий пароль</span><input class="input" type="password" name="current_password" required></label>
  <label class="field"><span>Новый пароль</span><input class="input" type="password" name="password" required minlength="8"></label>
  <label class="field"><span>Повтор</span><input class="input" type="password" name="password_confirmation" required minlength="8"></label>
  <button class="btn btn-primary" type="submit">Обновить пароль</button>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
