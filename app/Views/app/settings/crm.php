<?php require __DIR__ . '/_header.php'; ?>
<h2 class="section-title">CRM</h2>
<p class="text-secondary">Теги и категории клиентов настраиваются в CRM. Здесь — базовые предпочтения.</p>
<form class="stack form-narrow" data-api="/api/internal/settings/crm">
  <label class="check"><input type="checkbox" name="auto_create_client" value="1" checked> Автосоздание клиента при первом сообщении</label>
  <button class="btn btn-primary" type="submit">Сохранить</button>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
