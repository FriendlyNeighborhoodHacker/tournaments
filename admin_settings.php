<?php
require_once __DIR__.'/partials.php';
require_once __DIR__.'/settings.php';
require_admin();

$msg = null;
$err = null;

// Hard-coded keys and UI metadata
$SETTINGS_DEF = [
  'email pattern' => [
    'label' => 'Email domain pattern',
    'hint'  => 'Allowed email domain (e.g., hackleyschool.org). Subdomains are allowed automatically (e.g., students.hackleyschool.org).',
    'type'  => 'text', // single-line input
  ],
  'announcement' => [
    'label' => 'Announcement',
    'hint'  => 'Shown on the Home and Coach pages when non-empty.',
    'type'  => 'textarea',
  ],
  'new_user_message' => [
    'label' => 'New user message',
    'hint'  => 'Shown on Home for non-coach/admin users who have not signed up for any tournaments yet.',
    'type'  => 'textarea',
  ],
];

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  try {
    foreach ($SETTINGS_DEF as $key => $_meta) {
      $val = $_POST['s'][$key] ?? '';
      Settings::set($key, $val);
    }
    $msg = 'Settings saved.';
  } catch (Throwable $e) {
    $err = 'Failed to save settings.';
  }
}

// Gather current values
$current = [];
foreach ($SETTINGS_DEF as $key => $_meta) {
  // Provide sensible defaults
  $default = ($key === 'email pattern') ? 'hackleyschool.org' : '';
  $val = Settings::get($key, $default);
  // Backward-compat: if migrating from welcome_message -> new_user_message, prefill from legacy key
  if ($key === 'new_user_message' && $val === '') {
    $legacy = Settings::get('welcome_message', '');
    if ($legacy !== '') $val = $legacy;
  }
  $current[$key] = $val;
}

header_html('Manage Settings');
?>
<h2>Manage Settings</h2>
<?php if($msg):?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if($err):?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="card">
  <form method="post" class="stack">
    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <?php foreach($SETTINGS_DEF as $key => $meta): ?>
      <label>
        <?=h($meta['label'])?>
        <?php if (($meta['type'] ?? 'text') === 'textarea'): ?>
          <textarea name="s[<?=h($key)?>]" rows="4"><?=h($current[$key])?></textarea>
        <?php else: ?>
          <input type="text" name="s[<?=h($key)?>]" value="<?=h($current[$key])?>">
        <?php endif; ?>
        <?php if (!empty($meta['hint'])): ?>
          <small class="small"><?=h($meta['hint'])?></small>
        <?php endif; ?>
      </label>
    <?php endforeach; ?>
    <div class="actions">
      <button class="primary" type="submit">Save</button>
      <a class="button" href="/index.php">Cancel</a>
    </div>
  </form>
</div>

<?php footer_html(); ?>
