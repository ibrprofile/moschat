<?php require __DIR__ . '/_header.php'; ?>
<h2 class="section-title">Компания</h2>
<form class="stack form-narrow" data-api="/api/internal/settings/general">
  <label class="field"><span>Название</span><input class="input" name="name" value="<?= e($company['name'] ?? '') ?>"></label>
  <label class="field"><span>Сайт</span><input class="input" name="website" value="<?= e($company['website'] ?? '') ?>"></label>
  <label class="field"><span>Часовой пояс</span><input class="input" name="timezone" value="<?= e($company['timezone'] ?? '') ?>"></label>
  <button class="btn btn-primary" type="submit">Сохранить</button>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
