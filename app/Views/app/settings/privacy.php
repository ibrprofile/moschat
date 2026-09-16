<?php require __DIR__ . '/_header.php'; ?>
<h2 class="section-title">Privacy</h2>
<form class="stack form-narrow" data-api="/api/internal/settings/privacy">
  <label class="field"><span>Текст согласия в виджете</span>
    <textarea class="input" name="privacy_text" rows="4"><?= e($widgetSettings['privacy_text'] ?? '') ?></textarea>
  </label>
  <button class="btn btn-primary" type="submit">Сохранить</button>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
