<?php require __DIR__ . '/_header.php'; ?>
<h2 class="section-title">API</h2>
<p class="text-secondary">Создавайте токены для интеграций. Секрет показывается один раз.</p>
<div class="page-toolbar">
  <button type="button" class="btn btn-primary" id="create-token">Создать токен</button>
</div>
<div id="api-tokens" class="stack"></div>
<hr class="sep">
<h2 class="section-title">Webhooks</h2>
<div class="page-toolbar">
  <button type="button" class="btn btn-primary" id="create-webhook">Добавить webhook</button>
</div>
<div id="webhooks" class="stack"></div>
<?php require __DIR__ . '/_footer.php'; ?>
