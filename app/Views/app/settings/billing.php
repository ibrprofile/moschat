<?php require __DIR__ . '/_header.php';
$planKey = $plan['key'] ?? 'free';
?>
<h2 class="section-title">Тарифы</h2>
<div class="plans">
  <div class="plan-card <?= $planKey === 'free' ? 'is-current' : '' ?>">
    <h3>FREE</h3>
    <p class="plan-price">0 ₽/мес</p>
    <ul>
      <li>1 сайт</li>
      <li>1 сотрудник</li>
      <li>Базовый чат и CRM</li>
      <li>Брендинг MosChat</li>
    </ul>
    <?php if ($planKey !== 'free'): ?>
      <button class="btn btn-ghost" data-plan="free">Перейти на Free</button>
    <?php else: ?>
      <span class="badge">Текущий</span>
    <?php endif; ?>
  </div>
  <div class="plan-card <?= $planKey === 'pro' ? 'is-current' : '' ?>">
    <h3>PRO</h3>
    <p class="plan-price">399 ₽/мес</p>
    <ul>
      <li>До 10 сайтов</li>
      <li>До 25 сотрудников</li>
      <li>API, webhooks, отделы</li>
      <li>Без брендинга</li>
    </ul>
    <?php if ($planKey !== 'pro'): ?>
      <button class="btn btn-primary" data-plan="pro">Выбрать Pro</button>
    <?php else: ?>
      <span class="badge">Текущий</span>
    <?php endif; ?>
  </div>
</div>
<p class="text-meta">Оплата подключится позже — переключение тарифа сейчас активирует лимиты и функции.</p>
<?php require __DIR__ . '/_footer.php'; ?>
