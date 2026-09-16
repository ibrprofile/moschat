<?php require __DIR__ . '/_header.php'; ?>
<h2 class="section-title">Чат</h2>
<p class="text-secondary">Параметры поведения inbox и сохранённых ответов.</p>
<form class="stack form-narrow" data-api="/api/internal/settings/chat">
  <label class="field"><span>Автоназначение на себя при ответе</span>
    <select class="input" name="auto_assign_on_reply"><option value="1">Да</option><option value="0">Нет</option></select>
  </label>
  <button class="btn btn-primary" type="submit">Сохранить</button>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
