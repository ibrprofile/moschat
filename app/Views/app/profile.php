<div class="page-panel">
  <h2 class="section-title">Профиль</h2>
  <form class="stack form-narrow" data-api="/api/internal/settings/general" onsubmit="return false;">
    <label class="field"><span>Имя</span><input class="input" value="<?= e($user['name'] ?? '') ?>" disabled></label>
    <label class="field"><span>Email</span><input class="input" value="<?= e($user['email'] ?? '') ?>" disabled></label>
    <label class="field"><span>Часовой пояс</span><input class="input" value="<?= e($user['timezone'] ?? '') ?>" disabled></label>
  </form>
  <p class="text-secondary" style="margin-top:12px">Смена пароля — в <a href="/app/settings/security">Настройки → Security</a>.</p>
</div>
