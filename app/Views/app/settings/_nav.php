<?php
$section = $section ?? 'general';
$tabs = [
  'general' => 'General',
  'chat' => 'Chat',
  'widget' => 'Widget',
  'team' => 'Team',
  'crm' => 'CRM',
  'security' => 'Security',
  'billing' => 'Billing',
  'api' => 'API',
  'privacy' => 'Privacy',
];
?>
<div class="settings-layout">
  <nav class="settings-nav">
    <?php foreach ($tabs as $key => $label): ?>
      <a class="<?= $section === $key ? 'is-active' : '' ?>" href="/app/settings/<?= e($key) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="settings-body">
    <?= $content ?? '' ?>
  </div>
</div>
